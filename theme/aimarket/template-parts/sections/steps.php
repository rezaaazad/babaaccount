<?php
/**
 * Purchase steps: numbered bar + one card per step.
 *
 * @var array $args See aim_t_section_defaults( 'steps' ).
 */
defined( 'ABSPATH' ) || exit;
$a     = $args;
$total = count( $a['steps'] );
if ( ! $total ) {
	return;
}
?>
<section class="sec"<?php echo $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : ''; ?>>
	<div class="container">
		<?php aim_t_head( aim_t_title( $a['title_before'], $a['title_highlight'], $a['title_after'] ), $a['text'], '', $a['kicker'] ); ?>
		<div class="steps" data-steps data-last-url="<?php echo esc_url( $a['last_url'] ); ?>" data-next-text="<?php echo esc_attr( $a['next_text'] ); ?>" data-last-text="<?php echo esc_attr( $a['last_text'] ); ?>">
			<ol class="steps-bar">
				<?php foreach ( $a['steps'] as $i => $s ) : ?>
					<li><button type="button" class="<?php echo 0 === $i ? 'is-active' : ''; ?>" data-step="<?php echo (int) $i; ?>"><b><?php echo esc_html( aim_t_fa( $i + 1 ) ); ?></b><span><?php echo esc_html( $s['label'] ); ?></span></button></li>
				<?php endforeach; ?>
			</ol>
			<?php foreach ( $a['steps'] as $i => $s ) : ?>
				<div class="step-card" data-step-panel="<?php echo (int) $i; ?>" <?php echo 0 === $i ? '' : 'hidden'; ?>>
					<?php echo $s['art']; // phpcs:ignore WordPress.Security.EscapeOutput -- built-in SVG or wp_get_attachment_image(). ?>
					<div>
						<small>مرحلهٔ <?php echo esc_html( aim_t_fa( $i + 1 ) ); ?> از <?php echo esc_html( aim_t_fa( $total ) ); ?></small>
						<h3><?php echo esc_html( $s['title'] ); ?></h3>
						<p><?php echo esc_html( $s['text'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
			<div class="steps-nav">
				<button type="button" class="btn-ghost" data-step-go="-1"><i class="ic-arrow-right-s-line"></i><?php echo esc_html( $a['prev_text'] ); ?></button>
				<button type="button" class="pc-btn" data-step-go="1"><span><?php echo esc_html( $a['next_text'] ); ?></span><i class="ic-arrow-left-s-line"></i></button>
			</div>
		</div>
	</div>
</section>
