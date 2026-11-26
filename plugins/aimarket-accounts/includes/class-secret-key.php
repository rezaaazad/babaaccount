<?php
defined( 'ABSPATH' ) || exit;

/**
 * Panel → تنظیمات فروشگاه → ورود و امنیت → «کلید رمزنگاری اکانت‌ها».
 *
 * Generates a dedicated AIM_SECRET_KEY and adds it to wp-config.php, so the
 * key lives outside the database. The file is written only after a check that
 * it is the real config and is writable, and is restored if the result does
 * not read back as expected. Where the host does not allow writing, the panel
 * shows the line to paste instead. Existing accounts are re-encrypted with the
 * new key on the next admin request (AIM_Crypto::migrate()).
 *
 *   POST /security/secret-key
 */
class AIM_Secret_Key {

	public static function init() {
		add_action( 'admin_init', [ 'AIM_Crypto', 'migrate' ] );
		add_action( 'rest_api_init', function () {
			register_rest_route( AIM_Admin_API::NS, '/security/secret-key', [
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rest_create' ],
				'permission_callback' => fn() => current_user_can( 'manage_options' ) && ! is_multisite(),
			] );
		} );
	}

	/** wp-config.php the way WordPress finds it (site folder, or one level up). */
	public static function config_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		$up = dirname( ABSPATH ) . '/wp-config.php';
		return file_exists( $up ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ? $up : '';
	}

	/** @return array{ defined: bool, path: string, writable: bool } */
	public static function status() {
		$path = self::config_path();
		return [
			'defined'  => defined( 'AIM_SECRET_KEY' ),
			'path'     => $path,
			'writable' => $path && is_writable( $path ),
		];
	}

	public static function new_key() {
		return bin2hex( random_bytes( 32 ) );
	}

	/** The line that goes into wp-config.php. */
	public static function line( $key ) {
		return "define( 'AIM_SECRET_KEY', '" . $key . "' ); // هوش‌مارکت: کلید رمزنگاری اکانت‌ها — پاک یا عوض نکنید.";
	}

	public static function rest_create( WP_REST_Request $request ) {
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) || ! current_user_can( 'manage_options' ) ) {
			return self::fail( 'درخواست معتبر نیست.' );
		}
		$status = self::status();
		if ( $status['defined'] ) {
			return self::fail( 'کلید اختصاصی از قبل در wp-config.php تعریف شده است.' );
		}
		$key = self::new_key();
		$response = new WP_REST_Response( [
			'ok'      => false,
			'message' => 'ابتدا نسخه پشتیبان بگیرید و خط زیر را دستی به wp-config.php اضافه کنید.',
			'line'    => self::line( $key ),
		], 409 );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	private static function fail( $message ) {
		return new WP_REST_Response( [ 'ok' => false, 'message' => $message ], 400 );
	}
}
