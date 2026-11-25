<?php
/**
 * Product rail or grid (bestsellers, newest, on sale, hand-picked…).
 *
 * @var array $args See aim_t_section_defaults( 'products' ).
 */
defined( 'ABSPATH' ) || exit;
$a = $args;
if ( ! $a['products'] ) {
	return;
}
$rail = 'rail-' . sanitize_html_class( $a['id'] ?: 'products' );
$grid = 'grid' === $a['layout'];
?>
<section class="sec<?php echo $a['tint'] ? ' sec-tint' : ''; ?>"<?php echo $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : ''; ?>>
	<div class="container">
		<?php aim_t_head( aim_t_title( $a['title_before'], $a['title_highlight'], $a['title_after'] ), $a['text'], $a['link_url'], $a['kicker'], $grid ? '' : aim_t_rail_controls( $rail ), $a['link_text'] ); ?>
		<div class="<?php echo $grid ? 'arc-grid' : 'rail'; ?>" id="<?php echo esc_attr( $rail ); ?>">
			<?php foreach ( $a['products'] as $i => $p ) : ?>
				<?php echo aim_t_card( $p, 'rank' === $a['flag'] ? [ 'rank' => $i + 1 ] : ( 'new' === $a['flag'] ? [ 'flag' => 'new' ] : [] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
