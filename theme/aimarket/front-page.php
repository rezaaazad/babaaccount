<?php
/**
 * Homepage.
 *
 * Built with Elementor (the usual case): the page's own content, made of the
 * «هوش‌مارکت» widgets, so every text, image, link and section is editable.
 * Otherwise: the same section templates with their built-in content.
 */
defined( 'ABSPATH' ) || exit;
get_header();

if ( is_page() && aim_t_is_elementor_page( get_queried_object_id() ) ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
} else {
	$products = aim_t_section_defaults( 'products' );
	aim_t_section( 'hero' );
	aim_t_section( 'products', $products );
	aim_t_section( 'offer' );
	aim_t_section( 'finder' );
	aim_t_section( 'products', [
		'id'              => 'newest',
		'title_before'    => 'جدیدترین',
		'title_highlight' => 'سرویس‌ها',
		'text'            => 'سرویس‌هایی که تازه به فروشگاه اضافه شده‌اند.',
		'flag'            => 'new',
		'products'        => aim_t_section_products( 'newest', 8 ),
	] + $products );
	aim_t_section( 'steps' );
	aim_t_section( 'faq' );
	aim_t_section( 'posts' );
}

get_footer();
