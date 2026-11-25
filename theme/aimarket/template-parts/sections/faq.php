<?php
/**
 * Frequently asked questions (native <details>).
 *
 * @var array $args See aim_t_section_defaults( 'faq' ).
 */
defined( 'ABSPATH' ) || exit;
$a = $args;
if ( ! $a['items'] ) {
	return;
}
?>
<section class="sec<?php echo $a['tint'] ? ' sec-tint' : ''; ?>"<?php echo $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : ''; ?>>
	<div class="container faq-wrap">
		<?php echo $a['art']; // phpcs:ignore WordPress.Security.EscapeOutput -- built-in SVG or wp_get_attachment_image(). ?>
		<?php aim_t_head( aim_t_title( $a['title_before'], $a['title_highlight'], $a['title_after'] ), $a['text'] ); ?>
		<div class="faq">
			<?php foreach ( $a['items'] as $i => $f ) : ?>
				<details <?php echo 0 === $i && $a['open_first'] ? 'open' : ''; ?>>
					<summary><?php echo esc_html( $f['q'] ); ?><i class="ic-add-line" aria-hidden="true"></i></summary>
					<p><?php echo esc_html( $f['a'] ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>
