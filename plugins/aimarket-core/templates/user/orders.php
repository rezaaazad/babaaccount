<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$page   = max( 1, absint( $_GET['paged'] ?? 1 ) );
$result = wc_get_orders( [ 'customer_id' => get_current_user_id(), 'limit' => 15, 'paged' => $page, 'paginate' => true, 'orderby' => 'date', 'order' => 'DESC' ] );

aim_page_head( 'سفارشات من', '', 'user' );
?>
<section class="card">
	<?php if ( $result->orders ) : ?>
		<?php aim_template( 'user/_orders-table', [ 'orders' => $result->orders ] ); ?>
		<?php aim_pagination( $result->total, 15, $page ); ?>
	<?php elseif ( $page > 1 ) : // A page number past the last page (WooCommerce then reports no total either). ?>
		<?php aim_empty( 'shopping-bag-3-line', 'این صفحه سفارشی ندارد', '', '<a class="btn btn-primary" href="' . esc_url( aim_panel_url( 'user', 'orders' ) ) . '">صفحهٔ اول سفارش‌ها</a>' ); ?>
	<?php else : ?>
		<?php aim_empty( 'shopping-bag-3-line', 'هنوز سفارشی ثبت نکرده‌اید', '', '<a class="btn btn-primary" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">مشاهدهٔ سرویس‌ها</a>' ); ?>
	<?php endif; ?>
</section>
