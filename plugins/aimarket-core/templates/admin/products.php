<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.

$status   = sanitize_key( $_GET['status'] ?? '' );
$search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 20;

$args = [
	'limit'    => $per_page,
	'page'     => $page,
	'paginate' => true,
	'orderby'  => 'date',
	'order'    => 'DESC',
	'status'   => [ 'publish', 'draft', 'pending', 'private' ],
];
if ( in_array( $status, [ 'publish', 'draft', 'pending' ], true ) ) {
	$args['status'] = [ $status ];
} elseif ( in_array( $status, [ 'instock', 'outofstock' ], true ) ) {
	$args['stock_status'] = $status;
}
if ( '' !== $search ) {
	$args['s'] = $search;
}
$result = wc_get_products( $args );
$counts = wp_count_posts( 'product' );

$tabs = [
	''           => [ 'label' => 'همه', 'count' => (int) $counts->publish + (int) $counts->draft + (int) $counts->pending + (int) $counts->private ],
	'publish'    => [ 'label' => 'منتشرشده', 'count' => (int) $counts->publish ],
	'draft'      => [ 'label' => 'پیش‌نویس', 'count' => (int) $counts->draft ],
	'pending'    => [ 'label' => 'در انتظار تأیید', 'count' => (int) $counts->pending ],
	'instock'    => [ 'label' => 'موجود' ],
	'outofstock' => [ 'label' => 'ناموجود' ],
];

aim_page_head( 'محصولات', '<a class="btn btn-primary" href="' . esc_url( aim_panel_url( 'admin', 'product-new' ) ) . '">' . aim_icon( 'add-line' ) . 'ایجاد محصول</a>' );
?>
<div class="notice inline"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p>ساخت و ویرایش محصول داخل همین پنل و با همهٔ امکانات ووکامرس انجام می‌شود: پلن‌ها (متغیرها)، قیمت و تخفیف زمان‌دار، تصویر و گالری، دسته و برچسب، و تب «اکانت و تحویل». برای تغییر سریع قیمت چند محصول با هم از <a href="<?php echo esc_url( aim_panel_url( 'admin', 'bulk-prices' ) ); ?>">مدیریت گروهی قیمت</a> استفاده کنید.</p></div>
<section class="card">
	<?php aim_status_tabs( $tabs, $status ); ?>
	<form class="toolbar" method="get">
		<?php AIM_WP_Admin::form_fields(); ?>
		<?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
		<label class="search"><?php echo aim_icon( 'search-line' ); // phpcs:ignore ?><span class="visually-hidden">جستجو</span><input class="field" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="نام محصول…"></label>
		<button class="btn btn-light" type="submit">جستجو</button>
	</form>
	<?php if ( $result->products ) : ?>
		<?php
		/** Extra columns from modules: [ 'label' => header, 'cell' => fn( WC_Product ) => HTML ]; extra row buttons: 'aim_admin_product_actions'. */
		$columns = apply_filters( 'aim_admin_product_columns', [] );
		?>
		<div class="table-wrap">
			<table class="table">
				<thead><tr><th>تصویر</th><th>عنوان</th><th>قیمت</th><?php foreach ( $columns as $col ) : ?><th><?php echo esc_html( $col['label'] ); ?></th><?php endforeach; ?><th>موجودی</th><th>وضعیت</th><th>آخرین بروزرسانی</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $result->products as $p ) : ?>
					<?php
					// Stock count only when it is tracked: the product's own, or the sum of plans that track their own
					// stock. An in-stock plan without stock tracking is unlimited, so the product just shows «موجود».
					$stock = $p->managing_stock() ? (int) $p->get_stock_quantity() : null;
					if ( null === $stock && $p->is_type( 'variable' ) ) {
						foreach ( array_filter( array_map( 'wc_get_product', $p->get_children() ) ) as $c ) {
							if ( ! $c->get_manage_stock( 'edit' ) && $c->is_in_stock() ) {
								$stock = null;
								break;
							}
							if ( $c->get_manage_stock( 'edit' ) ) {
								$stock = (int) $stock + max( 0, (int) $c->get_stock_quantity() );
							}
						}
					}
					$range = $p->is_type( 'variable' ) && $p->get_variation_price( 'min' ) !== $p->get_variation_price( 'max' );
					?>
					<tr>
						<td><?php echo wp_kses_post( $p->get_image( [ 84, 84 ], [ 'class' => 'thumb' ] ) ); ?></td>
						<td><b><?php echo esc_html( $p->get_name() ); ?></b><small style="display:block"><?php echo esc_html( $p->is_type( 'variable' ) ? aim_fa_digits( count( $p->get_children() ) ) . ' متغیر' : 'ساده' ); ?></small></td>
						<td class="num"><?php echo $p->get_price() !== '' ? ( $range ? '<small>از</small> ' : '' ) . aim_price( $p->get_price() ) : '<span class="text-3">—</span>'; // phpcs:ignore ?></td>
						<?php foreach ( $columns as $col ) : ?><td><?php echo $col['cell']( $p ); // phpcs:ignore WordPress.Security.EscapeOutput -- module renders escaped HTML. ?></td><?php endforeach; ?>
						<td><?php echo $p->is_in_stock() ? '<span class="pill pill-success">' . esc_html( null !== $stock ? aim_fa_digits( $stock ) . ' عدد' : 'موجود' ) . '</span>' : '<span class="pill pill-danger">ناموجود</span>'; // phpcs:ignore ?></td>
						<td><?php echo 'publish' === $p->get_status() ? '<span class="pill pill-success">منتشرشده</span>' : '<span class="pill">' . esc_html( get_post_status_object( $p->get_status() )->label ?? $p->get_status() ) . '</span>'; // phpcs:ignore ?></td>
						<td class="num text-2"><?php echo esc_html( aim_jdate( $p->get_date_modified() ? $p->get_date_modified()->getTimestamp() : 0 ) ); ?></td>
						<td><div class="row-actions">
							<?php echo apply_filters( 'aim_admin_product_actions', '', $p ); // phpcs:ignore WordPress.Security.EscapeOutput -- module renders escaped HTML. ?>
							<a class="btn btn-light btn-xs" href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>" target="_blank" title="مشاهده"><?php echo aim_icon( 'eye-line' ); // phpcs:ignore ?></a>
							<a class="btn btn-light btn-xs" href="<?php echo esc_url( AIM_WP_Embed::url( 'post.php?post=' . $p->get_id() . '&action=edit', 'products' ) ); ?>" title="ویرایش"><?php echo aim_icon( 'edit-line' ); // phpcs:ignore ?>ویرایش</a>
						</div></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php aim_pagination( $result->total, $per_page, $page ); ?>
	<?php else : ?>
		<?php aim_empty( 'shopping-basket-2-line', 'محصولی پیدا نشد', '', '<a class="btn btn-primary" href="' . esc_url( aim_panel_url( 'admin', 'product-new' ) ) . '">' . aim_icon( 'add-line' ) . 'ایجاد محصول</a>' ); ?>
	<?php endif; ?>
</section>
