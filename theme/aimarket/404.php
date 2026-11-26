<?php
defined( 'ABSPATH' ) || exit;
status_header( 404 );
get_header();
$best = aim_t_cached_products( [ 'status' => 'publish', 'limit' => 4, 'orderby' => 'popularity' ] );
?>
<section class="sec">
	<div class="container">
		<div class="nf">
			<b class="nf-code">۴۰۴</b>
			<h1><?php echo esc_html( aim_t_opt( 'nf_title' ) ); ?></h1>
			<p><?php echo esc_html( aim_t_opt( 'nf_text' ) ); ?></p>
			<div class="pg-search"><?php get_search_form(); ?></div>
			<div class="co-done-actions">
				<a class="pc-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><i class="ic-home-5-line"></i>صفحهٔ نخست</a>
				<a class="btn-ghost" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">همهٔ سرویس‌ها</a>
			</div>
		</div>
		<?php if ( $best ) : ?>
			<div class="co-suggest">
				<?php aim_t_head( esc_html( aim_t_opt( 'nf_related' ) ) ); ?>
				<div class="arc-grid"><?php foreach ( $best as $p ) { echo aim_t_card( $p, [ 'compact' => true ] ); } // phpcs:ignore ?></div>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
