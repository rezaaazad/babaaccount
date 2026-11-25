<?php
/** Bulk price management: inline price table + increase/decrease tool (AIM_Bulk_Prices). */
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.

$cat    = absint( $_GET['cat'] ?? 0 );
$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$limit  = 200;
$args   = [ 'limit' => $limit, 'status' => [ 'publish', 'draft', 'pending', 'private' ], 'orderby' => 'title', 'order' => 'ASC' ];
if ( $cat && ( $term = get_term( $cat, 'product_cat' ) ) && ! is_wp_error( $term ) ) {
	$args['category'] = [ $term->slug ];
}
if ( '' !== $search ) {
	$args['s'] = $search;
}
$rows  = AIM_Bulk_Prices::rows( $args );
$total = wc_get_products( [ 'limit' => 1, 'paginate' => true, 'return' => 'ids', 'type' => [ 'simple', 'variable' ] ] + $args )->total;
$cats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );

aim_page_head( 'مدیریت گروهی قیمت' );
?>
<section class="card" data-prices>
	<div class="card-head"><h2><?php echo aim_icon( 'equalizer-line' ); // phpcs:ignore ?>تغییر گروهی</h2></div>
	<div class="card-body">
		<p class="text-2" style="margin-top:0">ردیف‌ها را از جدول پایین انتخاب کنید، عملیات را تنظیم کنید و «اعمال روی انتخاب‌شده‌ها» را بزنید. هر پلن یک ردیف جداست.</p>
		<div class="bulk-form">
			<label class="bulk-field"><span class="form-label">قیمت</span>
				<select class="field" data-bulk="field"><option value="regular">قیمت عادی</option><option value="sale">قیمت ویژه (تخفیف)</option></select>
			</label>
			<label class="bulk-field"><span class="form-label">عملیات</span>
				<select class="field" data-bulk="op"><option value="increase">افزایش</option><option value="decrease">کاهش</option><option value="set">تنظیم روی</option><option value="clear">حذف قیمت ویژه</option></select>
			</label>
			<label class="bulk-field"><span class="form-label">نوع</span>
				<select class="field" data-bulk="mode"><option value="percent">درصدی (٪)</option><option value="fixed">مبلغ ثابت (تومان)</option></select>
			</label>
			<label class="bulk-field"><span class="form-label">مقدار</span>
				<input class="field num" data-bulk="value" inputmode="numeric" placeholder="مثلاً ۱۰">
			</label>
			<label class="bulk-field"><span class="form-label">گرد کردن</span>
				<select class="field" data-bulk="round"><option value="0">بدون گرد کردن</option><option value="1000" selected>به هزار تومان</option><option value="10000">به ده هزار تومان</option></select>
			</label>
		</div>
		<p class="text-3 bulk-hint" data-bulk-hint></p>
		<div class="actions-row">
			<span class="text-2" data-selected-count>ردیفی انتخاب نشده</span>
			<span class="spacer"></span>
			<button type="button" class="btn btn-primary" data-bulk-apply disabled><?php echo aim_icon( 'check-line' ); // phpcs:ignore ?>اعمال روی انتخاب‌شده‌ها</button>
		</div>
	</div>
</section>

<section class="card" data-prices>
	<form class="toolbar" method="get">
		<?php AIM_WP_Admin::form_fields(); ?>
		<select class="field" name="cat" data-autosubmit aria-label="دسته‌بندی">
			<option value="0">همهٔ دسته‌ها</option>
			<?php foreach ( $cats as $c ) : ?>
				<option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( $cat, $c->term_id ); ?>><?php echo esc_html( $c->name . ' (' . aim_fa_digits( $c->count ) . ')' ); ?></option>
			<?php endforeach; ?>
		</select>
		<label class="search"><?php echo aim_icon( 'search-line' ); // phpcs:ignore ?><span class="visually-hidden">جستجو</span><input class="field" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="نام محصول…"></label>
		<button class="btn btn-light" type="submit">جستجو</button>
		<span class="spacer"></span>
		<button type="button" class="btn btn-primary" data-prices-save disabled><?php echo aim_icon( 'save-3-line' ); // phpcs:ignore ?>ذخیرهٔ تغییرات جدول</button>
	</form>
	<?php if ( $total > $limit ) : ?>
		<div class="notice inline notice-warning" style="margin:0 16px 12px"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p><?php echo esc_html( sprintf( 'فقط %1$s محصول از %2$s محصول نمایش داده شده است؛ برای دیدن بقیه، دسته‌بندی را انتخاب کنید یا جستجو کنید.', aim_fa_digits( $limit ), aim_fa_digits( $total ) ) ); ?></p></div>
	<?php endif; ?>
	<?php if ( $rows ) : ?>
		<div class="table-wrap">
			<table class="table prices-table">
				<thead><tr>
					<th class="check"><input type="checkbox" data-check-all aria-label="انتخاب همه"></th>
					<th>محصول</th><th>پلن</th><th>قیمت عادی (تومان)</th><th>قیمت ویژه (تومان)</th><th>موجودی</th><th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr data-row="<?php echo esc_attr( $r['id'] ); ?>">
						<td class="check"><input type="checkbox" data-check value="<?php echo esc_attr( $r['id'] ); ?>" aria-label="<?php echo esc_attr( 'انتخاب ' . $r['product'] . ' ' . $r['plan'] ); ?>"></td>
						<td><b><?php echo esc_html( $r['product'] ); ?></b></td>
						<td><?php echo $r['plan'] ? esc_html( $r['plan'] ) : '<span class="text-3">ساده</span>'; // phpcs:ignore ?></td>
						<td><input class="field num price-input" data-price="regular" inputmode="numeric" value="<?php echo esc_attr( $r['regular'] ); ?>" data-initial="<?php echo esc_attr( $r['regular'] ); ?>"></td>
						<td><input class="field num price-input" data-price="sale" inputmode="numeric" value="<?php echo esc_attr( $r['sale'] ); ?>" data-initial="<?php echo esc_attr( $r['sale'] ); ?>" placeholder="—"></td>
						<td><?php echo $r['in_stock'] ? '<span class="pill pill-success">' . esc_html( null === $r['stock'] ? 'موجود' : aim_fa_digits( $r['stock'] ) . ' عدد' ) . '</span>' : '<span class="pill pill-danger">ناموجود</span>'; // phpcs:ignore ?></td>
						<td><a class="btn btn-light btn-xs" href="<?php echo esc_url( AIM_WP_Embed::url( 'post.php?post=' . $r['edit_id'] . '&action=edit', 'products' ) ); ?>"><?php echo aim_icon( 'edit-line' ); // phpcs:ignore ?>ویرایش</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<?php aim_empty( 'shopping-basket-2-line', 'محصولی پیدا نشد' ); ?>
	<?php endif; ?>
</section>
