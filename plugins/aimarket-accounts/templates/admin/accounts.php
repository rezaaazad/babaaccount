<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.

$status   = sanitize_key( $_GET['status'] ?? '' );
$target   = preg_match( '/^\d+:\d+$/', $_GET['target'] ?? '' ) ? $_GET['target'] : ''; // phpcs:ignore
$search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 20;

$summary   = AIM_Inventory::summary();
$autos     = array_filter( $summary, fn( $s ) => 'auto' === $s['mode'] );
$threshold = (int) AIM_Settings::get( 'low_stock_threshold' );
$counts    = AIM_Inventory::status_counts( $target );
$result    = AIM_Inventory::query( [ 'status' => $status, 'target' => $target, 'search' => $search, 'page' => $page, 'per_page' => $per_page ] );
$labels    = [];
foreach ( $summary as $s ) {
	$labels[ $s['product_id'] . ':' . $s['variation_id'] ] = $s['label'];
}

$tabs = [ '' => [ 'label' => 'همه', 'count' => $counts[''] ] ];
foreach ( AIM_Inventory::STATUSES as $k => [ $label ] ) {
	$tabs[ $k ] = [ 'label' => $label, 'count' => $counts[ $k ] ];
}

$actions = $autos
	? '<button class="btn btn-primary" data-modal-open="add-accounts">' . aim_icon( 'add-line' ) . 'افزودن اکانت</button>'
	: '';
aim_page_head( 'انبار اکانت', $actions );
?>

<?php if ( ! $summary ) : ?>
	<section class="card">
		<?php
		aim_empty(
			'key-2-line',
			'هنوز محصول اکانتی ندارید',
			'در ویرایشگر محصول، تب «اکانت و تحویل» را باز کنید و گزینهٔ «محصول اکانت است» را فعال کنید. بعد از ذخیره، محصول اینجا نمایش داده می‌شود.',
			'<a class="btn btn-primary" href="' . esc_url( aim_panel_url( 'admin', 'product-new' ) ) . '">' . aim_icon( 'add-line' ) . 'ساخت محصول</a>'
		);
		?>
	</section>
	<?php return; ?>
<?php endif; ?>

<?php if ( $autos ) : ?>
<div class="grid grid-3 mb-20">
	<?php foreach ( $autos as $s ) : ?>
		<?php
		$key   = $s['product_id'] . ':' . $s['variation_id'];
		$pct   = $s['total'] ? round( $s['available'] / $s['total'] * 100 ) : 0;
		$state = ! $s['available'] ? 'is-empty' : ( $s['available'] <= $threshold ? 'is-low' : '' );
		?>
		<a class="card card-pad" href="<?php echo esc_url( add_query_arg( [ 'target' => $key, 'paged' => false, 'status' => false ] ) ); ?>" style="color:inherit;<?php echo $target === $key ? 'border-color:var(--primary)' : ''; ?>">
			<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
				<span class="dot-ico" style="width:36px;height:36px;border-radius:10px;display:grid;place-items:center;background:var(--primary-50);color:var(--primary)"><?php echo aim_icon( 'key-2-line' ); // phpcs:ignore ?></span>
				<b style="font-size:13px;line-height:1.6"><?php echo esc_html( $s['label'] ); ?></b>
			</div>
			<div style="display:flex;justify-content:space-between;font-size:12px" class="text-2">
				<span><b style="font-size:20px;color:var(--text)"><?php echo esc_html( aim_fa_digits( $s['available'] ) ); ?></b> آماده</span>
				<span><?php echo esc_html( aim_fa_digits( $s['assigned'] ) ); ?> تحویل‌شده</span>
			</div>
			<div class="progress <?php echo esc_attr( $state ); ?>" style="margin-top:8px"><span style="width:<?php echo esc_attr( max( $s['available'] ? 4 : 0, $pct ) ); ?>%"></span></div>
			<?php if ( 'is-empty' === $state ) : ?><small style="color:var(--danger)">تمام شده — محصول در فروشگاه ناموجود است</small><?php elseif ( 'is-low' === $state ) : ?><small style="color:var(--warning)">موجودی رو به اتمام</small><?php endif; ?>
		</a>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<section class="card">
	<?php aim_status_tabs( $tabs, $status ); ?>
	<form class="toolbar" method="get">
		<?php AIM_WP_Admin::form_fields(); ?>
		<?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
		<label class="search">
			<?php echo aim_icon( 'search-line' ); // phpcs:ignore ?>
			<span class="visually-hidden">جستجو</span>
			<input class="field" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="شناسهٔ اکانت، شمارهٔ سفارش یا یادداشت…">
		</label>
		<label class="visually-hidden" for="acc-target">محصول</label>
		<select id="acc-target" name="target" data-autosubmit>
			<option value="">همهٔ محصولات</option>
			<?php foreach ( $summary as $s ) : ?>
				<?php $k = $s['product_id'] . ':' . $s['variation_id']; ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $target, $k ); ?>><?php echo esc_html( $s['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="btn btn-light" type="submit">اعمال</button>
		<?php if ( $target || $search ) : ?><a class="btn btn-ghost" href="<?php echo esc_url( aim_panel_url( 'admin', 'accounts' ) ); ?>">حذف فیلترها</a><?php endif; ?>
	</form>

	<?php if ( $result['rows'] ) : ?>
		<div class="table-wrap">
			<table class="table">
				<thead><tr><th>شناسه</th><th>محصول</th><th>اطلاعات اکانت</th><th>یادداشت</th><th>وضعیت</th><th>سفارش / مشتری</th><th>انقضا</th><th>افزوده‌شده</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $result['rows'] as $r ) : ?>
					<?php $cust = $r->user_id ? get_userdata( (int) $r->user_id ) : null; ?>
					<tr>
						<td class="num">#<?php echo esc_html( aim_fa_digits( $r->id ) ); ?></td>
						<td style="min-width:160px"><?php echo esc_html( $labels[ $r->product_id . ':' . $r->variation_id ] ?? AIM_Inventory::product_label( (int) $r->product_id, (int) $r->variation_id ) ); ?></td>
						<td><span class="secret ltr" id="sec-<?php echo esc_attr( $r->id ); ?>">•••••••• محرمانه</span></td>
						<td class="text-2"><?php echo esc_html( $r->note ?: '—' ); ?></td>
						<td><?php echo AIM_Inventory::status_pill( $r->status ); // phpcs:ignore ?></td>
						<td>
							<?php if ( $r->order_id ) : ?>
								<a href="<?php echo esc_url( aim_panel_url( 'admin', 'orders', [ 'order' => $r->order_id ] ) ); ?>">#<?php echo esc_html( aim_fa_digits( $r->order_id ) ); ?></a>
								<small class="ltr" style="display:block"><?php echo esc_html( $cust ? aim_fa_digits( aim_user_phone( $cust->ID ) ?: $cust->display_name ) : '' ); ?></small>
							<?php else : ?>
								<span class="text-3">—</span>
							<?php endif; ?>
						</td>
						<td class="num text-2"><?php echo esc_html( $r->expires_at ? aim_jdate( $r->expires_at ) : ( (int) $r->duration_days ? aim_fa_digits( $r->duration_days ) . ' روز پس از تحویل' : 'بدون انقضا' ) ); ?></td>
						<td class="num text-2"><?php echo esc_html( aim_jdate( $r->created_at ) ); ?></td>
						<td>
							<div class="row-actions">
								<button class="btn btn-light btn-xs" data-action="accounts/<?php echo esc_attr( $r->id ); ?>/reveal" data-reveal-target="#sec-<?php echo esc_attr( $r->id ); ?>" title="نمایش اطلاعات"><?php echo aim_icon( 'eye-line' ); // phpcs:ignore ?></button>
								<button class="btn btn-light btn-xs" data-copy="#sec-<?php echo esc_attr( $r->id ); ?>" title="کپی (پس از نمایش)"><?php echo aim_icon( 'file-copy-line' ); // phpcs:ignore ?></button>
								<?php if ( 'available' === $r->status ) : ?>
									<button class="btn btn-light btn-xs" data-action="accounts/<?php echo esc_attr( $r->id ); ?>/status" data-body='{"status":"disabled"}' title="غیرفعال کردن"><?php echo aim_icon( 'forbid-line' ); // phpcs:ignore ?></button>
								<?php elseif ( 'disabled' === $r->status ) : ?>
									<button class="btn btn-success btn-xs" data-action="accounts/<?php echo esc_attr( $r->id ); ?>/status" data-body='{"status":"available"}' title="فعال کردن"><?php echo aim_icon( 'checkbox-circle-line' ); // phpcs:ignore ?></button>
								<?php endif; ?>
								<?php if ( 'assigned' !== $r->status ) : ?>
									<button class="btn btn-danger btn-xs" data-action="accounts/<?php echo esc_attr( $r->id ); ?>/delete" data-confirm="این اکانت برای همیشه حذف شود؟" title="حذف"><?php echo aim_icon( 'delete-bin-6-line' ); // phpcs:ignore ?></button>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php aim_pagination( $result['total'], $per_page, $page ); ?>
	<?php else : ?>
		<?php aim_empty( 'inbox-line', 'اکانتی پیدا نشد', $counts[''] ? 'فیلترها را تغییر دهید.' : 'اولین اکانت‌ها را با دکمهٔ «افزودن اکانت» وارد کنید.', $counts[''] || ! $autos ? '' : '<button class="btn btn-primary" data-modal-open="add-accounts">' . aim_icon( 'add-line' ) . 'افزودن اکانت</button>' ); ?>
	<?php endif; ?>
</section>

<?php if ( $autos ) : ?>
<div class="modal modal-lg" id="add-accounts" hidden role="dialog" aria-modal="true" aria-labelledby="add-acc-title">
	<form class="modal-dialog" data-api="accounts/add" data-reload>
		<div class="modal-head">
			<h3 id="add-acc-title">افزودن اکانت به انبار</h3>
			<button type="button" class="btn btn-ghost btn-icon btn-sm" data-modal-close aria-label="بستن"><?php echo aim_icon( 'close-line' ); // phpcs:ignore ?></button>
		</div>
		<div class="modal-body">
			<div class="form-row">
				<label class="form-label" for="add-target">محصول</label>
				<select id="add-target" name="target" required>
					<?php foreach ( $autos as $s ) : ?>
						<?php $k = $s['product_id'] . ':' . $s['variation_id']; ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $target, $k ); ?>><?php echo esc_html( $s['label'] . ' — ' . aim_fa_digits( $s['available'] ) . ' موجود' ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="form-row">
				<span class="form-label">هر اکانت در چند خط است؟</span>
				<div class="radio-cards">
					<label class="radio-card"><input type="radio" name="mode" value="line" checked>یک خط<small>هر خط = یک اکانت</small></label>
					<label class="radio-card"><input type="radio" name="mode" value="block">چند خط<small>اکانت‌ها با خط خالی یا --- جدا شوند</small></label>
				</div>
			</div>
			<div class="form-row">
				<label class="form-label" for="add-secrets">اطلاعات اکانت‌ها <span class="text-3" data-acc-count>(۰ اکانت)</span></label>
				<textarea id="add-secrets" class="ltr" name="secrets" rows="9" required placeholder="user1@mail.com:Pass123&#10;user2@mail.com:Pass456"></textarea>
				<p class="form-help">اطلاعات پس از ذخیره رمزنگاری می‌شود. موجودی محصول در فروشگاه خودکار به‌روز می‌شود.</p>
			</div>
			<div class="form-row" style="margin:0">
				<label class="form-label" for="add-note">یادداشت (اختیاری)</label>
				<input id="add-note" class="field" name="note" maxlength="250" placeholder="مثال: خرید از تأمین‌کننده الف — مهر ۱۴۰۵">
			</div>
		</div>
		<div class="modal-foot">
			<button type="button" class="btn btn-light" data-modal-close>انصراف</button>
			<button type="submit" class="btn btn-primary"><?php echo aim_icon( 'add-line' ); // phpcs:ignore ?>افزودن به انبار</button>
		</div>
	</form>
</div>
<?php endif; ?>
