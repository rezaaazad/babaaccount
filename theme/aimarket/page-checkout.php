<?php
/**
 * Checkout and order-received page (classic checkout, our templates in
 * woocommerce/checkout/). Checkout has its own two-step header.
 */
defined( 'ABSPATH' ) || exit;
get_header();
if ( is_wc_endpoint_url( 'order-received' ) ) {
	aim_t_checkout_head( 'سفارش ثبت شد', 'done' );
} else {
	?>
	<section class="cx-head">
		<div class="container">
			<div class="cx-bar">
				<a class="cx-back" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><i class="ic-arrow-right-line"></i>بازگشت به سبد</a>
				<ol class="cx-steps">
					<li class="is-active"><b>۱</b><span><strong>اطلاعات و سفارش</strong><small>بررسی مشخصات و مبلغ</small></span></li>
					<li><b>۲</b><span><strong>پرداخت امن</strong><small>انتقال به درگاه بانکی</small></span></li>
				</ol>
				<span class="cx-secure"><i class="ic-shield-check-line"></i>پرداخت امن و رمزنگاری‌شده</span>
			</div>
			<h1><?php echo aim_t_mark( aim_t_opt( 'co_title' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by aim_t_mark(). ?></h1>
		</div>
	</section>
	<?php
}
echo '<section class="sec co-body"><div class="container">' . do_shortcode( '[woocommerce_checkout]' ) . '</div></section>'; // phpcs:ignore
get_footer();
