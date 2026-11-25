<?php
/**
 * "What do you need AI for?" — use-case tabs, each with its own products.
 *
 * @var array $args See aim_t_section_defaults( 'finder' ).
 */
defined( 'ABSPATH' ) || exit;
$a   = $args;
$uid = sanitize_html_class( $a['id'] ?: 'finder' );
if ( ! $a['uses'] ) {
	return;
}
?>
<section class="sec"<?php echo $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : ''; ?>>
	<div class="container">
		<div class="finder" data-finder>
			<span class="finder-ico" aria-hidden="true"><i class="ic-sparkling-2-line"></i></span>
			<?php aim_t_head( aim_t_title( $a['title_before'], $a['title_highlight'], $a['title_after'] ), $a['text'] ); ?>
			<div class="finder-tabs" role="tablist">
				<?php foreach ( $a['uses'] as $i => $u ) : ?>
					<button type="button" role="tab" class="finder-tab<?php echo 0 === $i ? ' is-active' : ''; ?>" data-use="<?php echo esc_attr( $uid . '-' . $i ); ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>">
						<?php echo $u['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- icon markup from aim_t_icon() or Elementor's icon renderer. ?><span><?php echo esc_html( $u['label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<?php if ( $a['ask'] ) : ?>
				<form class="finder-ask" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label for="<?php echo esc_attr( $uid ); ?>-s"><?php echo esc_html( $a['ask_label'] ); ?></label>
					<div>
						<input id="<?php echo esc_attr( $uid ); ?>-s" type="search" name="s" placeholder="<?php echo esc_attr( $a['ask_placeholder'] ); ?>">
						<input type="hidden" name="post_type" value="product">
						<button type="submit" aria-label="جست‌وجو"><i class="ic-arrow-left-line"></i></button>
					</div>
				</form>
			<?php endif; ?>
			<?php foreach ( $a['uses'] as $i => $u ) : ?>
				<div class="finder-panel" data-use-panel="<?php echo esc_attr( $uid . '-' . $i ); ?>" <?php echo 0 === $i ? '' : 'hidden'; ?>>
					<div class="finder-top">
						<?php echo $u['art']; // phpcs:ignore WordPress.Security.EscapeOutput -- built-in SVG or wp_get_attachment_image(). ?>
						<div>
							<span class="finder-label"><?php echo $u['icon']; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $u['label'] ); ?></span>
							<h3><?php echo esc_html( $u['title'] ); ?></h3>
							<p><?php echo esc_html( $u['text'] ); ?></p>
						</div>
					</div>
					<ul class="finder-list">
						<?php foreach ( $u['products'] as $p ) : ?>
							<?php [ $price ] = aim_t_prices( $p ); ?>
							<li>
								<a href="<?php echo esc_url( $p->get_permalink() ); ?>">
									<?php echo aim_t_logo( $p, 'fl-logo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<span class="fl-txt"><b><?php echo esc_html( $p->get_name() ); ?></b><small><?php echo esc_html( $p->get_meta( '_aim_subtitle' ) ?: wp_trim_words( wp_strip_all_tags( $p->get_short_description() ), 8, '…' ) ); ?></small></span>
									<span class="fl-price"><small>از</small><b><?php echo esc_html( aim_t_money( $price ) ); ?></b><small>تومان</small></span>
									<span class="fl-cta"><?php echo esc_html( $a['cta'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
