<?php
defined( 'ABSPATH' ) || exit;

/**
 * Small site-wide options from panel → تنظیمات فروشگاه:
 *  - custom code (head HTML, footer HTML, CSS, JS) kept in its own option
 *    (aim_custom_code) because it must not pass through text sanitising;
 *  - front-end optimisation switches;
 *  - a "next purchase" coupon on the order-received page.
 *
 *   POST /settings/code { head, footer, css, js }   (needs unfiltered_html)
 */
class AIM_Site_Tweaks {

	const CODE_OPTION = 'aim_custom_code';
	const CODE_KEYS   = [ 'head', 'footer', 'css', 'js' ];

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'wp_head', [ __CLASS__, 'print_head' ], 99 );
		add_action( 'wp_footer', [ __CLASS__, 'print_footer' ], 99 );
		add_action( 'woocommerce_thankyou', [ __CLASS__, 'thankyou_coupon' ], 5 );
		add_action( 'init', [ __CLASS__, 'optimise' ] );
	}

	public static function routes() {
		register_rest_route( AIM_Admin_API::NS, '/site/rebuild-home', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'rest_rebuild_home' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );
		register_rest_route( AIM_Admin_API::NS, '/settings/code', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'rest_save_code' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' ),
		] );
	}

	public static function code() {
		return wp_parse_args( (array) get_option( self::CODE_OPTION, [] ), array_fill_keys( self::CODE_KEYS, '' ) );
	}

	public static function rest_save_code( WP_REST_Request $req ) {
		$code = [];
		foreach ( self::CODE_KEYS as $key ) {
			$value        = $req->get_param( $key );
			$code[ $key ] = is_scalar( $value ) ? trim( (string) $value ) : '';
		}
		// <style>/<script> wrappers belong to the page; the CSS and JS boxes take bare code.
		if ( preg_match( '~</?(style|script)\b~i', $code['css'] . $code['js'] ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'در کادر CSS و JS فقط خودِ کد را بنویسید، بدون تگ <style> یا <script>.' ], 400 );
		}
		update_option( self::CODE_OPTION, $code, true );
		AIM_Activity::log( 'custom_code_saved', 'ذخیرهٔ کدهای سفارشی', 'settings' );
		return rest_ensure_response( [ 'ok' => true, 'message' => 'کدهای سفارشی ذخیره شد.' ] );
	}

	/** Rebuild the Elementor homepage from the theme's built-in sections (theme function). */
	public static function rest_rebuild_home() {
		if ( ! function_exists( 'aim_t_elementor_install' ) || ! did_action( 'elementor/loaded' ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'افزونهٔ المنتور یا قالب هوش‌مارکت فعال نیست.' ], 400 );
		}
		$id = aim_t_elementor_install( true );
		AIM_Activity::log( 'elementor_home', 'ساخت دوبارهٔ صفحهٔ اصلی المنتور', 'page', $id );
		return rest_ensure_response( [ 'ok' => true, 'message' => 'صفحهٔ اصلی تازه ساخته و صفحهٔ اول سایت شد. صفحهٔ قبلی در «برگه‌ها» می‌ماند.' ] );
	}

	public static function print_head() {
		$code = self::code();
		if ( '' !== $code['css'] ) {
			echo "\n<style id=\"aim-custom-css\">\n" . $code['css'] . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- saved by an administrator with unfiltered_html.
		}
		if ( '' !== $code['head'] ) {
			echo "\n" . $code['head'] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- same.
		}
	}

	public static function print_footer() {
		$code = self::code();
		if ( '' !== $code['footer'] ) {
			echo "\n" . $code['footer'] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- same.
		}
		if ( '' !== $code['js'] ) {
			echo "\n<script id=\"aim-custom-js\">\n" . $code['js'] . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- same.
		}
	}

	/** Front-end only: the admin and the editors keep everything they rely on. */
	public static function optimise() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		$s = AIM_Settings::all();
		if ( $s['opt_disable_emojis'] ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			add_filter( 'emoji_svg_url', '__return_false' );
		}
		if ( $s['opt_disable_embeds'] ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
			add_action( 'wp_enqueue_scripts', fn() => wp_deregister_script( 'wp-embed' ), 100 );
		}
		if ( $s['opt_jquery_migrate'] ) {
			// Not 'wp_default_scripts': WP_Scripts is usually set up before 'init', so that hook has already run.
			add_action( 'wp_enqueue_scripts', function () {
				$scripts = wp_scripts();
				if ( isset( $scripts->registered['jquery'] ) ) {
					$scripts->registered['jquery']->deps = array_values( array_diff( $scripts->registered['jquery']->deps, [ 'jquery-migrate' ] ) );
				}
			}, 0 );
		}
		if ( $s['opt_remove_ver'] ) {
			remove_action( 'wp_head', 'wp_generator' ); // <meta name="generator" content="WordPress x.y">, which shows the same version.
			$strip = fn( $src ) => $src && str_contains( $src, 'ver=' . get_bloginfo( 'version' ) ) ? remove_query_arg( 'ver', $src ) : $src;
			add_filter( 'style_loader_src', $strip );
			add_filter( 'script_loader_src', $strip );
		}
	}

	public static function thankyou_coupon( $order_id ) {
		$code  = trim( (string) AIM_Settings::get( 'thankyou_coupon' ) );
		$order = wc_get_order( $order_id );
		if ( '' === $code || ! $order || $order->has_status( 'failed' ) ) {
			return;
		}
		$coupon = new WC_Coupon( $code );
		// Only a coupon the customer can still use: published, not expired, uses left.
		if ( ! $coupon->get_id() || 'publish' !== $coupon->get_status()
			|| ( $coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time() )
			|| ( $coupon->get_usage_limit() && $coupon->get_usage_count() >= $coupon->get_usage_limit() )
			|| (float) $coupon->get_amount() <= 0 ) { // e.g. a free-shipping coupon: nothing to show as «… تخفیف»
			return;
		}
		$amount = 'percent' === $coupon->get_discount_type() ? str_replace( '.', '٫', aim_fa_digits( (float) $coupon->get_amount() ) ) . '٪' : wc_price( $coupon->get_amount() );
		?>
		<div class="aim-ty-coupon">
			<div>
				<small><?php echo esc_html( AIM_Settings::get( 'thankyou_coupon_text' ) ); ?></small>
				<b><?php echo wp_kses_post( $amount ); ?> تخفیف</b>
			</div>
			<button type="button" class="aim-ty-code" data-copy-code="<?php echo esc_attr( $coupon->get_code() ); ?>"><span><?php echo esc_html( strtoupper( $coupon->get_code() ) ); ?></span><i class="ic-file-copy-line" aria-hidden="true"></i></button>
		</div>
		<?php
	}
}
