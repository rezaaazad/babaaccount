<?php
/**
 * Brand: the HooshMarket logo (assets/brand/, cut from the logo sheet with a
 * transparent background) in the header, menu, footer, panel, login page,
 * contract and as the favicon. The name is set as text in the logo's style —
 * first word in the accent gradient — so it follows light and dark mode.
 */
defined( 'ABSPATH' ) || exit;

function aim_t_brand_url( $file ) {
	return get_template_directory_uri() . '/assets/brand/' . $file;
}

/** The logo symbol as an <img>. */
function aim_t_brand_mark( $class = 'brand-logo' ) {
	return sprintf( '<img class="%s" src="%s" alt="" width="308" height="290" decoding="async">', esc_attr( $class ), esc_url( aim_t_brand_url( 'mark.webp' ) ) );
}

/**
 * Site name in the logo's two colours: the first word (up to the first space or
 * zero-width non-joiner, which stays with it so the letters don't join) in the accent.
 */
function aim_t_brand_name( $name ) {
	$name = (string) $name;
	if ( ! preg_match( '/^(\S+?\x{200C}|\S+\s)(.+)$/u', $name, $m ) ) {
		return '<span class="bn-a">' . esc_html( $name ) . '</span>';
	}
	return '<span class="bn-a">' . esc_html( $m[1] ) . '</span>' . esc_html( $m[2] );
}

// Panel, login page and customer area (aimarket-core).
add_filter( 'aim_brand_mark', fn() => aim_t_brand_mark( 'brand-mark is-img' ) );
add_filter( 'aim_brand_logo_url', fn( $url ) => $url ?: aim_t_brand_url( 'mark-512.png' ) );

// Favicon and home-screen icon, unless one is set in Appearance → Customize → Site Identity.
function aim_t_favicon() {
	if ( has_site_icon() ) {
		return;
	}
	printf( '<link rel="icon" type="image/png" sizes="32x32" href="%s">' . "\n", esc_url( aim_t_brand_url( 'favicon-32.png' ) ) );
	printf( '<link rel="icon" type="image/png" sizes="192x192" href="%s">' . "\n", esc_url( aim_t_brand_url( 'mark-192.png' ) ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( aim_t_brand_url( 'app-icon-180.png' ) ) );
	echo '<meta name="theme-color" content="#2f56f5">' . "\n";
}
foreach ( [ 'wp_head', 'admin_head', 'login_head', 'aim_head' ] as $hook ) {
	add_action( $hook, 'aim_t_favicon' );
}
