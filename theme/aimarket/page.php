<?php
/**
 * Regular WordPress pages (about, terms, privacy, …).
 */
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	aim_t_page_head( get_the_title(), [ get_the_title() => '' ] );
	?>
	<section class="sec">
		<div class="container pg-narrow">
			<article <?php post_class( 'pg-card entry-content' ); ?>>
				<?php the_content(); ?>
				<?php wp_link_pages( [ 'before' => '<nav class="arc-pages">', 'after' => '</nav>' ] ); ?>
			</article>
			<?php if ( comments_open() || get_comments_number() ) : ?>
				<?php comments_template(); ?>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;
get_footer();
