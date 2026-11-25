<?php
/**
 * Latest journal posts.
 *
 * @var array $args See aim_t_section_defaults( 'posts' ).
 */
defined( 'ABSPATH' ) || exit;
$a = $args;
if ( ! $a['posts'] ) {
	return;
}
?>
<section class="sec"<?php echo $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : ''; ?>>
	<div class="container">
		<?php aim_t_head( aim_t_title( $a['title_before'], $a['title_highlight'], $a['title_after'] ), $a['text'], $a['link_url'], $a['kicker'], '', $a['link_text'] ); ?>
		<div class="posts">
			<?php foreach ( $a['posts'] as $post_item ) : ?>
				<?php echo aim_t_post_card( $post_item ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
