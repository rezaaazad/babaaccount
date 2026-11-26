<?php
defined( 'ABSPATH' ) || exit;

add_filter( 'rest_endpoints', static function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( '/wp/v2/users' === $route || str_starts_with( $route, '/wp/v2/users/' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
	}
	return $endpoints;
} );
add_filter( 'wp_sitemaps_add_provider', static fn( $provider, $name ) => 'users' === $name ? false : $provider, 10, 2 );
add_action( 'template_redirect', static function () {
	if ( ! is_user_logged_in() && isset( $_GET['author'] ) ) { // Public, read-only query.
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
}, 1 );
add_action( 'send_headers', static function () {
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
	header_remove( 'X-Powered-By' );
} );
add_action( 'after_setup_theme', static function () {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
} );
add_filter( 'the_generator', '__return_empty_string' );
add_filter( 'rest_post_dispatch', static function ( $response, $server, $request ) {
	if ( preg_match( '#^/aimarket/v1/(minicart|auth|alerts|me|nonce|security)(/|$)#', $request->get_route() ) ) {
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'Vary', 'Cookie', false );
	}
	return $response;
}, 10, 3 );
