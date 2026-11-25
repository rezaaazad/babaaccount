<?php
/**
 * Blog home, category/tag/date archives and post search.
 */
defined( 'ABSPATH' ) || exit;
get_header();

if ( is_search() ) {
	$title = 'نتایج جست‌وجو برای «' . get_search_query() . '»';
	$sub   = 'مقاله‌ها و صفحه‌هایی که با عبارت جست‌وجوی تو مطابقت دارند.';
} elseif ( is_archive() ) {
	$title = wp_strip_all_tags( get_the_archive_title() );
	$sub   = wp_strip_all_tags( get_the_archive_description() );
} else {
	$title = 'مجله و آموزش';
	$sub   = 'راهنمای انتخاب، آموزش استفاده و خبرهای ابزارهای هوش مصنوعی.';
}
aim_t_page_head( $title, [ $title => '' ], $sub );
?>
<section class="sec">
	<div class="container">
		<?php if ( is_search() || is_home() ) : ?>
			<div class="pg-search"><?php get_search_form(); ?></div>
		<?php endif; ?>
		<?php if ( have_posts() ) : ?>
			<div class="posts">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php echo aim_t_post_card(); // phpcs:ignore ?>
				<?php endwhile; ?>
			</div>
			<?php aim_t_paginate(); ?>
		<?php else : ?>
			<div class="arc-empty">
				<?php echo aim_t_illus( 'research', 'co-art' ); // phpcs:ignore ?>
				<h2><?php echo is_search() ? 'نتیجه‌ای پیدا نشد' : 'هنوز مطلبی منتشر نشده'; ?></h2>
				<p><?php echo is_search() ? 'عبارت دیگری را امتحان کن یا سراغ سرویس‌ها برو.' : 'به‌زودی مقاله‌ها و آموزش‌ها اینجا قرار می‌گیرند.'; ?></p>
				<a class="pc-btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">مشاهدهٔ سرویس‌ها</a>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
