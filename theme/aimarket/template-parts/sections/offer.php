<?php
/**
 * Discount banner with a copyable code.
 *
 * @var array $args See aim_t_section_defaults( 'offer' ).
 */
defined( 'ABSPATH' ) || exit;
$a = $args;
?>
<section class="sec sec-tight"<?php echo $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : ''; ?>>
	<div class="container">
		<div class="offer">
			<div class="offer-main">
				<?php if ( $a['art'] ) : ?><span class="offer-art"><?php echo $a['art']; // phpcs:ignore WordPress.Security.EscapeOutput -- built-in SVG or wp_get_attachment_image(). ?></span><?php endif; ?>
				<div>
					<?php if ( $a['kicker'] ) : ?><span class="offer-kicker"><i class="ic-sparkling-line"></i><?php echo esc_html( $a['kicker'] ); ?></span><?php endif; ?>
					<?php if ( $a['amount'] ) : ?><strong class="offer-amount"><?php echo esc_html( $a['amount'] ); ?></strong><?php endif; ?>
					<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
					<?php if ( $a['text'] ) : ?><p><?php echo esc_html( $a['text'] ); ?></p><?php endif; ?>
				</div>
			</div>
			<div class="offer-side">
				<?php if ( $a['code'] ) : ?>
					<div class="offer-code">
						<small><?php echo esc_html( $a['code_label'] ); ?></small>
						<b class="ltr"><?php echo esc_html( $a['code'] ); ?></b>
						<button type="button" data-copy-text="<?php echo esc_attr( $a['code'] ); ?>"><i class="ic-file-copy-line"></i><span><?php echo esc_html( $a['copy_text'] ); ?></span></button>
					</div>
				<?php endif; ?>
				<?php if ( $a['points'] ) : ?>
					<ul class="offer-list">
						<?php foreach ( $a['points'] as $point ) : ?><li><i class="ic-check-line"></i><?php echo esc_html( $point ); ?></li><?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
