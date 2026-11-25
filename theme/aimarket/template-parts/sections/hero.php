<?php
/**
 * Hero: kicker, title, text, search, popular tags, and the 3D artwork (light/dark).
 * Two columns from 992px; below, the artwork sits on top of the centred text.
 * Light and dark modes each have their own look and artwork.
 *
 * @var array $args See aim_t_section_defaults( 'hero' ).
 */
defined( 'ABSPATH' ) || exit;
$a = $args;
?>
<section class="hero" id="<?php echo esc_attr( $a['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $a['id'] ); ?>-title">
	<div class="hero-grid" aria-hidden="true"></div>
	<?php foreach ( array_slice( $a['chips'], 0, 6 ) as $i => $chip ) : ?>
		<a class="hero-chip chip-<?php echo (int) $i + 1; ?>" href="<?php echo esc_url( $chip['url'] ?: '#' ); ?>" tabindex="-1" aria-hidden="true">
			<?php echo $chip['logo']; // phpcs:ignore WordPress.Security.EscapeOutput -- product logo SVG or wp_get_attachment_image(). ?><span><?php echo esc_html( $chip['label'] ); ?></span>
		</a>
	<?php endforeach; ?>

	<svg class="hero-waves" viewBox="0 0 1440 640" preserveAspectRatio="none" aria-hidden="true" focusable="false">
		<path class="w1" d="M0 470C190 400 330 520 560 480S930 330 1130 380 1360 470 1440 440V640H0Z"/>
		<path class="w2" d="M0 540C220 470 420 600 700 560S1100 450 1290 500 1440 560 1440 560V640H0Z"/>
		<path class="w3" d="M0 320C260 260 360 420 620 400S960 240 1440 300"/>
		<path class="w3" d="M0 380C240 330 420 470 680 450S1020 300 1440 360"/>
	</svg>

	<div class="container hero-in">
		<div class="hero-copy">
		<?php if ( $a['kicker'] ) : ?><span class="hero-kicker"><i></i><b class="ic-shopping-bag-3-line" aria-hidden="true"></b><?php echo esc_html( $a['kicker'] ); ?></span><?php endif; ?>
		<h1 id="<?php echo esc_attr( $a['id'] ); ?>-title"><span class="t1"><?php echo esc_html( $a['title_before'] ); ?></span> <?php if ( '' !== (string) $a['title_highlight'] ) : ?><em><?php echo esc_html( $a['title_highlight'] ); ?></em><?php endif; ?> <span class="t2"><?php echo esc_html( $a['title_after'] ); ?></span></h1>
		<?php if ( $a['text'] ) : ?><p class="hero-text"><?php echo esc_html( $a['text'] ); ?></p><?php endif; ?>

		<?php if ( $a['search'] ) : ?>
			<form class="hero-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<i class="ic-search-line" aria-hidden="true"></i>
				<label class="visually-hidden" for="<?php echo esc_attr( $a['id'] ); ?>-s"><?php echo esc_html( $a['button'] ); ?></label>
				<input id="<?php echo esc_attr( $a['id'] ); ?>-s" type="search" name="s" placeholder="<?php echo esc_attr( $a['placeholder'] ); ?>" autocomplete="off">
				<input type="hidden" name="post_type" value="product">
				<button type="submit"><b class="ic-search-line" aria-hidden="true"></b><?php echo esc_html( $a['button'] ); ?></button>
			</form>
		<?php endif; ?>

		<?php if ( $a['tags'] ) : ?>
			<div class="hero-tags">
				<?php if ( $a['tags_label'] ) : ?><span><?php echo esc_html( $a['tags_label'] ); ?></span><?php endif; ?>
				<?php foreach ( $a['tags'] as $tag ) : ?>
					<a href="<?php echo esc_url( $tag['url'] ?: add_query_arg( [ 's' => $tag['text'], 'post_type' => 'product' ], home_url( '/' ) ) ); ?>"><?php echo aim_t_tag_logo( $tag['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- image tag / SVG tile. ?><span dir="auto"><?php echo esc_html( $tag['text'] ); ?></span></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( $a['hint_text'] ) : ?>
			<a class="hero-finder" href="<?php echo esc_url( $a['hint_url'] ?: '#' ); ?>"><i class="ic-sparkling-line"></i><?php echo esc_html( $a['hint_text'] ); ?><i class="ic-arrow-left-s-line"></i></a>
		<?php endif; ?>
		</div>

		<?php if ( $a['art_light'] || $a['art_dark'] ) : ?>
			<?php // Both load lazily and the hidden one is display:none, so only the artwork of the current mode is downloaded; phones get the 800px copy. ?>
			<figure class="hero-art" aria-hidden="true">
				<?php foreach ( [ 'light' => $a['art_light'] ?: $a['art_dark'], 'dark' => $a['art_dark'] ?: $a['art_light'] ] as $mode => $src ) : ?>
					<img class="is-<?php echo esc_attr( $mode ); ?>" src="<?php echo esc_url( $src ); ?>"<?php echo aim_t_hero_srcset( $src ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url(). ?> alt="" width="1536" height="1024" loading="lazy" decoding="async">
				<?php endforeach; ?>
			</figure>
		<?php endif; ?>
	</div>

	<?php if ( $a['scroll_text'] ) : ?>
		<a class="hero-scroll" href="<?php echo esc_url( $a['scroll_url'] ?: '#' ); ?>"><span><?php echo esc_html( $a['scroll_text'] ); ?></span><i class="ic-arrow-down-s-line"></i></a>
	<?php endif; ?>
</section>
