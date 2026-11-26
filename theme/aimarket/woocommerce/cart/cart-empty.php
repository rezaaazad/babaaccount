<?php
/**
 * Empty cart.
 */
defined( 'ABSPATH' ) || exit;
do_action( 'woocommerce_cart_is_empty' );
$best = aim_t_cached_products( [ 'status' => 'publish', 'limit' => 4, 'orderby' => 'popularity' ] );
?>
<div class="co-empty">
	<?php echo aim_t_illus( 'cart', 'co-art' ); // phpcs:ignore ?>
	<h2><?php echo esc_html( aim_t_opt( 'c_empty_title' ) ); ?></h2>
	<p><?php echo esc_html( aim_t_opt( 'c_empty_text' ) ); ?></p>
	<a class="pc-btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><i class="ic-store-2-line"></i><?php echo esc_html( aim_t_opt( 'c_empty_button' ) ); ?></a>
</div>
<?php if ( $best ) : ?>
	<div class="co-suggest">
		<?php aim_t_head( 'پرفروش‌ترین‌ها' ); ?>
		<div class="arc-grid">
			<?php foreach ( $best as $p ) : ?><?php echo aim_t_card( $p, [ 'compact' => true ] ); // phpcs:ignore ?><?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>
