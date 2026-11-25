<?php
defined( 'ABSPATH' ) || exit;

/**
 * Native wp-admin screens (product editor, coupons, WooCommerce settings,
 * media, …) shown inside the store panel.
 *
 * The panel renders the screen in an <iframe> (templates/admin/embed.php).
 * Every admin page gets a tiny head script: when it finds itself framed by
 * the panel it adds html.aim-embed, which hides WordPress's own menu, toolbar
 * and footer and applies the panel look (assets/css/wp-embed.css). Outside
 * the panel the stylesheet matches nothing, so wp-admin itself is unchanged.
 */
class AIM_WP_Embed {

	public static function init() {
		add_action( 'admin_head', [ __CLASS__, 'head' ], 0 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
	}

	/**
	 * Admin path from the request (?path=…), or '' when missing or unsafe.
	 * Only "file.php" or "file.php?query" inside wp-admin is accepted, and
	 * never the panel itself (it would nest).
	 */
	public static function request_path() {
		$path = isset( $_GET['path'] ) ? wp_unslash( $_GET['path'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		return self::safe_path( $path );
	}

	public static function safe_path( $path ) {
		$path = trim( (string) $path );
		if ( ! preg_match( '~^[a-z0-9_-]+\.php(\?[^#\s]*)?$~i', $path ) ) {
			return '';
		}
		parse_str( (string) wp_parse_url( $path, PHP_URL_QUERY ), $query );
		if ( 'admin.php' === strtok( $path, '?' ) && AIM_WP_Admin::SLUG === ( $query['page'] ?? '' ) ) {
			return '';
		}
		return $path;
	}

	/** Panel URL that opens $path inside $section (the sidebar keeps that section highlighted). */
	public static function url( $path, $section = 'wordpress' ) {
		return AIM_WP_Admin::url( $section, [ 'path' => rawurlencode( $path ) ] ); // add_query_arg() does not encode values.
	}

	public static function head() {
		if ( AIM_WP_Admin::is_panel() ) {
			return;
		}
		// Runs before first paint, so a framed page never flashes the WordPress chrome.
		?>
		<script>
		(function () {
			try {
				if (window.top === window.self || !window.top.document.querySelector('.aim-shell')) return;
				var html = document.documentElement, top = window.top.document.documentElement;
				html.classList.add('aim-embed');
				var sync = function () { if (top.dataset.theme) html.dataset.theme = top.dataset.theme; else html.removeAttribute('data-theme'); };
				sync();
				new MutationObserver(sync).observe(top, { attributes: true, attributeFilter: ['data-theme'] });
			} catch (e) { /* framed by another origin: leave the page alone */ }
		})();
		</script>
		<?php
	}

	public static function assets() {
		if ( AIM_WP_Admin::is_panel() ) {
			return;
		}
		$fonts = apply_filters( 'aim_font_css', 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css' );
		wp_enqueue_style( 'aim-font', $fonts, [], null );
		wp_enqueue_style( 'aim-wp-embed', AIM_URL . 'assets/css/wp-embed.css', [], AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/css/wp-embed.css' ) );
		wp_enqueue_script( 'aim-wp-embed', AIM_URL . 'assets/js/wp-embed.js', [], AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/js/wp-embed.js' ), true );
	}

	/**
	 * WordPress's admin menu (core + every plugin), for the «همهٔ بخش‌های وردپرس» page.
	 * Returns [ [ 'label', 'icon', 'path', 'children' => [ [ 'label', 'path' ] ] ] ].
	 */
	public static function wp_menu() {
		global $menu, $submenu;
		$out = [];
		foreach ( (array) $menu as $entry ) {
			[ $label, $cap, $slug ] = array_pad( (array) $entry, 3, '' );
			if ( ! $label || ! current_user_can( $cap ) || AIM_WP_Admin::SLUG === $slug || str_starts_with( (string) $slug, 'separator' ) ) {
				continue;
			}
			$children = [];
			foreach ( $submenu[ $slug ] ?? [] as $sub ) {
				if ( ! current_user_can( $sub[1] ) ) {
					continue;
				}
				$path = self::safe_path( self::menu_path( $sub[2], $slug ) );
				if ( $path ) {
					$children[] = [ 'label' => self::clean_label( $sub[0] ), 'path' => $path ];
				}
			}
			// Like wp-admin's own sidebar: a menu with submenus opens its first submenu (the bare
			// WooCommerce page "admin.php?page=woocommerce" is not loadable and shows an error).
			$path = ( $children[0]['path'] ?? '' ) ?: self::safe_path( self::menu_path( $slug, '' ) );
			if ( $path ) {
				$out[] = [ 'label' => self::clean_label( $label ), 'icon' => $entry[6] ?? '', 'path' => $path, 'children' => $children ];
			}
		}
		return $out;
	}

	/** Menu slug → admin path, the way WordPress builds menu links. */
	private static function menu_path( $slug, $parent ) {
		if ( str_contains( $slug, '.php' ) ) {
			return $slug;
		}
		if ( $parent && str_contains( $parent, '.php' ) && ! str_contains( $parent, 'admin.php' ) ) {
			return $parent . ( str_contains( $parent, '?' ) ? '&' : '?' ) . 'page=' . $slug;
		}
		return 'admin.php?page=' . $slug;
	}

	/** Menu labels end with counter bubbles ("Comments <span…>3</span>"); keep the text before them. */
	private static function clean_label( $label ) {
		$text = trim( wp_strip_all_tags( preg_replace( '~<span.*$~s', '', (string) $label ) ) );
		return '' !== $text ? $text : trim( wp_strip_all_tags( (string) $label ) );
	}
}
