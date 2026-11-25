<?php
/**
 * Row of trust badges (instant delivery, guarantee, …).
 *
 * @var array $args See aim_t_section_defaults( 'trust' ).
 */
defined( 'ABSPATH' ) || exit;
if ( empty( $args['items'] ) ) {
	return;
}
?>
<ul class="trust">
	<?php foreach ( $args['items'] as $t ) : ?>
		<li><?php echo $t['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- icon markup. ?><div><b><?php echo esc_html( $t['title'] ); ?></b><small><?php echo esc_html( $t['text'] ); ?></small></div></li>
	<?php endforeach; ?>
</ul>
