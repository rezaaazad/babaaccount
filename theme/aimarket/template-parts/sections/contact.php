<?php
/**
 * Contact page block: heading + contact cards (support, phone, email, hours…).
 *
 * @var array $args See aim_t_section_defaults( 'contact' ).
 */
defined( 'ABSPATH' ) || exit;
$a = $args;
?>
<section class="sec"<?php echo $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : ''; ?>>
	<div class="container">
		<?php aim_t_head( aim_t_title( $a['title_before'], $a['title_highlight'], $a['title_after'] ), $a['text'], '', $a['kicker'] ); ?>
		<?php if ( $a['items'] ) : ?>
			<ul class="contact-cards">
				<?php foreach ( $a['items'] as $c ) : ?>
					<li>
						<?php if ( $c['url'] ) : ?><a href="<?php echo esc_url( $c['url'], [ 'http', 'https', 'tel', 'mailto' ] ); ?>"><?php else : ?><div><?php endif; ?>
							<span class="contact-ico"><?php echo $c['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- icon markup. ?></span>
							<small><?php echo esc_html( $c['title'] ); ?></small>
							<b<?php echo preg_match( '/^[\d\s+()\-۰-۹@.a-z]+$/iu', $c['value'] ) ? ' class="ltr"' : ''; ?>><?php echo esc_html( $c['value'] ); ?></b>
						<?php echo $c['url'] ? '</a>' : '</div>'; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
