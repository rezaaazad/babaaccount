<?php
defined( 'ABSPATH' ) || exit;

/**
 * Front-end routes served by the plugin, outside the theme:
 *   /login/                     phone + OTP login
 *   /panel/admin/{section}/     admin panel (manage_woocommerce)
 *   /panel/user/{section}/      customer panel (any logged-in user)
 *   /panel/logout/              nonce-protected logout
 */
class AIM_Router {

	/** Set when the rewrite rules must be rebuilt on the next request. */
	const FLUSH_OPTION = 'aim_flush_rewrite';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
		add_action( 'wp_loaded', [ __CLASS__, 'maybe_flush' ] );
		add_filter( 'query_vars', [ __CLASS__, 'query_vars' ] );
		add_action( 'template_redirect', [ __CLASS__, 'dispatch' ], 1 );

		// WooCommerce "My account" → our user panel.
		add_action( 'template_redirect', [ __CLASS__, 'redirect_my_account' ] );
		add_filter( 'woocommerce_get_myaccount_page_permalink', fn() => aim_panel_url( 'user' ) );
	}

	/**
	 * Rebuild the rewrite rules after activation, or when saved rules lack the panel's routes or
	 * WooCommerce's endpoints (a flush made before WooCommerce loaded sends "order-received" to
	 * the homepage). Runs at wp_loaded, after every plugin added its rules.
	 */
	public static function maybe_flush() {
		if ( ! get_option( 'permalink_structure' ) ) {
			return;
		}
		if ( ( get_option( self::FLUSH_OPTION ) || ! self::rules_ok() ) && ! get_transient( 'aim_rewrite_flushed' ) ) {
			flush_rewrite_rules( false );
			delete_option( self::FLUSH_OPTION );
			// Still incomplete (e.g. another plugin was mid-activation): try again in a few minutes,
			// without rebuilding on every request meanwhile.
			if ( ! self::rules_ok() ) {
				set_transient( 'aim_rewrite_flushed', 1, 5 * MINUTE_IN_SECONDS );
			}
		}
	}

	/** Saved rules contain the panel routes and, with WooCommerce, its page endpoints. */
	private static function rules_ok() {
		$rules = get_option( 'rewrite_rules' );
		return is_array( $rules ) && isset( $rules['^panel/(admin|user)/?$'] )
			&& ( ! class_exists( 'WooCommerce' ) || str_contains( implode( ' ', $rules ), 'order-received=' ) );
	}

	public static function add_rewrite_rules() {
		add_rewrite_rule( '^login/?$', 'index.php?aim_page=login', 'top' );
		add_rewrite_rule( '^panel/logout/?$', 'index.php?aim_page=logout', 'top' );
		add_rewrite_rule( '^panel/(admin|user)/?$', 'index.php?aim_page=panel&aim_panel=$matches[1]', 'top' );
		add_rewrite_rule( '^panel/(admin|user)/([a-z0-9-]+)/?$', 'index.php?aim_page=panel&aim_panel=$matches[1]&aim_section=$matches[2]', 'top' );
	}

	public static function query_vars( $vars ) {
		return array_merge( $vars, [ 'aim_page', 'aim_panel', 'aim_section' ] );
	}

	public static function dispatch() {
		$page = get_query_var( 'aim_page' );
		if ( ! $page ) {
			return;
		}
		nocache_headers();

		if ( 'login' === $page ) {
			// Owners can open /login/?preview=1 to see the page while signed in.
			$preview = isset( $_GET['preview'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( is_user_logged_in() && ! $preview ) {
				$default = aim_panel_url( 'user' );
				$to = (string) wp_unslash( $_GET['redirect_to'] ?? '' ); // phpcs:ignore WordPress.Security
				wp_safe_redirect( '' === $to ? $default : wp_validate_redirect( $to, $default ) );
				exit;
			}
			self::render( 'login', [ 'redirect' => esc_url_raw( wp_unslash( $_GET['redirect_to'] ?? '' ) ) ] ); // phpcs:ignore WordPress.Security
		}

		if ( 'logout' === $page ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'aim_logout' ) ) {
				wp_logout();
			}
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( 'panel' === $page ) {
			$panel   = get_query_var( 'aim_panel' ) === 'admin' ? 'admin' : 'user';
			$section = sanitize_key( get_query_var( 'aim_section' ) ?: 'dashboard' );

			// The store admin panel is not served on the front end any more: old links
			// go to its wp-admin page (managers) or to the customer panel (everyone else).
			if ( 'admin' === $panel ) {
				if ( current_user_can( 'manage_woocommerce' ) ) {
					$args = array_map( 'sanitize_text_field', array_filter( wp_unslash( $_GET ), 'is_string' ) ); // phpcs:ignore WordPress.Security.NonceVerification
					wp_safe_redirect( AIM_WP_Admin::url( $section, $args ) );
				} else {
					wp_safe_redirect( is_user_logged_in() ? aim_panel_url( 'user' ) : aim_login_url() );
				}
				exit;
			}

			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( aim_login_url( aim_panel_url( 'user', 'dashboard' === $section ? '' : $section ) ) );
				exit;
			}

			$menu = AIM_Panel_Menu::user();
			$item = AIM_Panel_Menu::find( $menu, $section, false );
			if ( ! $item && ! class_exists( 'WooCommerce' ) && in_array( $section, [ 'orders', 'accounts', 'favorites' ], true ) ) {
				$item = [ 'key' => 'needs-woocommerce', 'label' => 'سفارشات من' ]; // Store sections while the store is off.
			}
			if ( ! $item ) {
				status_header( 404 );
				$item = [ 'key' => '404', 'label' => 'صفحه پیدا نشد', 'soon' => true ];
			}

			self::render( 'panel/layout-user', [
				'menu'    => $menu,
				'section' => $section,
				'item'    => $item,
			] );
		}
	}

	public static function redirect_my_account() {
		// Customers sign in with a one-time code, so WooCommerce's account and lost-password screens are never shown.
		// Also catch sub-paths (e.g. /my-account/orders/) even when their rewrite rules are missing and WordPress resolved a 404.
		$under_account = false;
		$account_id    = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'myaccount' ) : 0;
		if ( is_404() && $account_id > 0 ) {
			$base          = trim( (string) wp_parse_url( get_permalink( $account_id ), PHP_URL_PATH ), '/' );
			$path          = trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security
			$under_account = '' !== $base && str_starts_with( rawurldecode( $path ) . '/', rawurldecode( $base ) . '/' );
		}
		if ( $under_account || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			wp_safe_redirect( is_user_logged_in() ? aim_panel_url( 'user' ) : aim_login_url() );
			exit;
		}
	}

	private static function render( $template, $vars ) {
		if ( '404' !== ( $vars['item']['key'] ?? '' ) ) {
			status_header( 200 );
		}
		aim_template( $template, $vars );
		exit;
	}

	/** Shared <head> assets for plugin-rendered pages. */
	public static function head_assets( $bundle ) {
		$fonts = apply_filters( 'aim_font_css', 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css' );
		$icons = apply_filters( 'aim_icon_css', AIM_URL . 'assets/css/icons.css?ver=' . filemtime( AIM_DIR . 'assets/css/icons.css' ) );
		$ver   = AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/css/panel.css' );
		printf( '<link rel="stylesheet" href="%s">' . "\n", esc_url( $fonts ) );
		printf( '<link rel="stylesheet" href="%s">' . "\n", esc_url( $icons ) );
		printf( '<link rel="stylesheet" href="%s">' . "\n", esc_url( AIM_URL . 'assets/css/panel.css?ver=' . $ver ) );
		$config = [
			'rest'    => esc_url_raw( rest_url( 'aimarket/v1/' ) ),
			'nonce'   => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'home'    => home_url( '/' ),
			'bundle'  => $bundle,
		];
		printf( '<script>window.AIM=%s;</script>' . "\n", wp_json_encode( $config ) );
		wp_site_icon();
		do_action( 'aim_head' ); // e.g. the theme's favicon when no site icon is set
		// Apply the saved colour mode before first paint to avoid a flash.
		echo aim_color_mode_script(); // phpcs:ignore
	}

	public static function footer_assets( $bundle ) {
		$ver = AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/js/panel.js' );
		printf( '<script src="%s" defer></script>' . "\n", esc_url( AIM_URL . 'assets/js/panel.js?ver=' . $ver ) );
		if ( 'login' === $bundle ) {
			$ver = AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/js/login.js' );
			printf( '<script src="%s" defer></script>' . "\n", esc_url( AIM_URL . 'assets/js/login.js?ver=' . $ver ) );
		}
	}

	public static function logout_url() {
		return wp_nonce_url( home_url( '/panel/logout/' ), 'aim_logout' );
	}
}
