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

	public static function rest_create() {
		$status = self::status();
		if ( $status['defined'] ) {
			return self::fail( 'کلید اختصاصی از قبل در wp-config.php تعریف شده است.' );
		}
		$key = self::new_key();
		if ( ! $status['writable'] ) {
			return new WP_REST_Response( [
				'ok'      => false,
				'message' => 'هاست اجازهٔ تغییر wp-config.php را نمی‌دهد. خط زیر را دستی اضافه کنید.',
				'line'    => self::line( $key ),
			], 409 );
		}

		$path     = $status['path'];
		$original = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $original || ! preg_match( '/^\s*<\?php/', $original ) || ! str_contains( $original, 'DB_NAME' ) ) {
			return self::fail( 'فایل wp-config.php شناخته نشد؛ چیزی تغییر نکرد.' );
		}
		if ( str_contains( $original, 'AIM_SECRET_KEY' ) ) {
			return self::fail( 'در wp-config.php از قبل خطی با AIM_SECRET_KEY هست؛ آن را بررسی کنید.' );
		}

		// Right after the opening tag: before anything that loads WordPress.
		$updated = preg_replace( '/^(\s*<\?php[^\n]*\n)/', '$1' . self::line( $key ) . "\n", $original, 1 );
		if ( false === file_put_contents( $path, $updated, LOCK_EX ) || file_get_contents( $path ) !== $updated ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $path, $original, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return self::fail( 'نوشتن در wp-config.php کامل نشد و فایل به حالت قبل برگشت.' );
		}
		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $path, true );
		}

		AIM_Activity::log( 'secret_key_created', 'کلید اختصاصی رمزنگاری در wp-config.php ثبت شد', 'settings' );
		return rest_ensure_response( [
			'ok'      => true,
			'message' => 'کلید ساخته و در wp-config.php ثبت شد. اطلاعات اکانت‌های فعلی خودکار با کلید جدید رمز می‌شوند.',
			'key'     => $key,
		] );
	}

	private static function fail( $message ) {
		return new WP_REST_Response( [ 'ok' => false, 'message' => $message ], 400 );
	}
}
