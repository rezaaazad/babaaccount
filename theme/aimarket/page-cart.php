<?php
/**
 * Cart page. Renders WooCommerce's classic cart (our template in
 * woocommerce/cart/) regardless of the blocks saved in the page content.
 */
defined( 'ABSPATH' ) || exit;
get_header();
aim_t_checkout_head( 'سبد خرید', 'cart' );
echo '<section class="sec co-body"><div class="container">' . do_shortcode( '[woocommerce_cart]' ) . '</div></section>'; // phpcs:ignore
get_footer();
