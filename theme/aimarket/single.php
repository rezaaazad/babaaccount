<?php
/**
 * Single blog post.
 */
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$cats  = get_the_category();
	$blog  = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/?post_type=post' );
	$trail = [ 'مجله' => $blog ];
	if ( $cats ) {
		$trail[ $cats[0]->name ] = get_category_link( $cats[0] );
	}
	$trail[ get_the_title() ] = '';
	$words   = count( preg_split( '/\s+/u', trim( wp_strip_all_tags( get_the_content() ) ), -1, PREG_SPLIT_NO_EMPTY ) ?: [] );
	$minutes = max( 1, (int) ceil( $words / 200 ) );
	?>
	<section class="pg-head pg-head-post">
		<div class="container pg-narrow">
			<?php aim_t_breadcrumb( $trail ); ?>
			<?php if ( $cats ) : ?><a class="pd-cat pg-cat" href="<?php echo esc_url( get_category_link( $cats[0] ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a><?php endif; ?>
			<h1><?php the_title(); ?></h1>
			<div class="pg-meta">
				<span><i class="ic-user-3-line"></i><?php the_author(); ?></span>
				<span><i class="ic-calendar-line"></i><?php echo esc_html( function_exists( 'aim_jdate' ) ? aim_jdate( get_post_time( 'U', true ) ) : get_the_date() ); ?></span>
				<span><i class="ic-time-line"></i><?php echo esc_html( aim_t_fa( $minutes ) ); ?> دقیقه مطالعه</span>
			</div>
		</div>
	</section>
	<section class="sec">
		<div class="container pg-narrow">
			<?php if ( has_post_thumbnail() ) : ?><figure class="pg-cover"><?php the_post_thumbnail( 'large' ); ?></figure><?php endif; ?>
			<article <?php post_class( 'pg-card entry-content' ); ?>>
				<?php the_content(); ?>
				<?php wp_link_pages( [ 'before' => '<nav class="arc-pages">', 'after' => '</nav>' ] ); ?>
			</article>
			<?php $tags = get_the_tags(); ?>
			<?php if ( $tags ) : ?>
				<div class="pg-tags"><?php foreach ( $tags as $t ) : ?><a href="<?php echo esc_url( get_tag_link( $t ) ); ?>">#<?php echo esc_html( $t->name ); ?></a><?php endforeach; ?></div>
			<?php endif; ?>
			<nav class="pg-nav">
				<?php $prev = get_previous_post(); $next = get_next_post(); ?>
				<?php if ( $prev ) : ?><a href="<?php echo esc_url( get_permalink( $prev ) ); ?>"><small><i class="ic-arrow-right-line"></i>مطلب قبلی</small><b><?php echo esc_html( get_the_title( $prev ) ); ?></b></a><?php else : ?><span></span><?php endif; ?>
				<?php if ( $next ) : ?><a class="is-next" href="<?php echo esc_url( get_permalink( $next ) ); ?>"><small>مطلب بعدی<i class="ic-arrow-left-line"></i></small><b><?php echo esc_html( get_the_title( $next ) ); ?></b></a><?php endif; ?>
			</nav>
			<?php if ( comments_open() || get_comments_number() ) : ?>
				<?php comments_template(); ?>
			<?php endif; ?>
		</div>
	</section>
	<?php
	$related = get_posts( [ 'numberposts' => 4, 'post__not_in' => [ get_the_ID() ], 'category__in' => wp_list_pluck( $cats, 'term_id' ) ] );
	if ( $related ) :
		?>
		<section class="sec sec-tint">
			<div class="container">
				<?php aim_t_head( 'مطالب <em>مرتبط</em>' ); ?>
				<div class="posts"><?php foreach ( $related as $rp ) { echo aim_t_post_card( $rp ); } // phpcs:ignore ?></div>
			</div>
		</section>
		<?php
	endif;
endwhile;
get_footer();
