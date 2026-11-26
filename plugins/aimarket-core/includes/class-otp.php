<?php
defined( 'ABSPATH' ) || exit;

/**
 * Phone + one-time-code login. Unknown numbers are registered on first
 * successful verify (login and sign-up are one flow). Password login is a
 * secondary path for users who have set a password.
 *
 * REST routes (namespace aimarket/v1):
 *   POST /auth/send      { phone }                → sends a code
 *   POST /auth/verify    { phone, code, redirect } → logs in
 *   POST /auth/password  { phone, password, redirect }
 */
class AIM_OTP {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	public static function routes() {
		$common = [ 'methods' => 'POST', 'permission_callback' => '__return_true' ];
		register_rest_route( 'aimarket/v1', '/auth/send', $common + [ 'callback' => [ __CLASS__, 'rest_send' ] ] );
		register_rest_route( 'aimarket/v1', '/auth/verify', $common + [ 'callback' => [ __CLASS__, 'rest_verify' ] ] );
		register_rest_route( 'aimarket/v1', '/auth/password', $common + [ 'callback' => [ __CLASS__, 'rest_password' ] ] );
	}

	public static function rest_send( WP_REST_Request $req ) {
		$phone = aim_normalize_phone( $req->get_param( 'phone' ) );
		if ( ! $phone ) {
			return self::error( 'شمارهٔ موبایل معتبر نیست. نمونهٔ درست: ۰۹۱۲۳۴۵۶۷۸۹' );
		}
		$result = self::send( $phone );
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message(), $result->get_error_data()['wait'] ?? null );
		}
		return rest_ensure_response( [
			'ok'      => true,
			'phone'   => $phone,
			'ttl'     => (int) AIM_Settings::get( 'otp_ttl' ),
			'wait'    => (int) AIM_Settings::get( 'otp_resend_wait' ),
			'length'  => (int) AIM_Settings::get( 'otp_length' ),
			'message' => 'کد تأیید به ' . aim_fa_digits( $phone ) . ' ارسال شد.',
		] );
	}

	public static function rest_verify( WP_REST_Request $req ) {
		$phone = aim_normalize_phone( $req->get_param( 'phone' ) );
		$code  = is_scalar( $req->get_param( 'code' ) ) ? preg_replace( '/\D/', '', aim_en_digits( (string) $req->get_param( 'code' ) ) ) : '';
		if ( ! $phone || ! $code ) {
			return self::error( 'شماره یا کد وارد نشده است.' );
		}
		$check = self::verify( $phone, $code );
		if ( is_wp_error( $check ) ) {
			return self::error( $check->get_error_message() );
		}

		$user_id = aim_get_user_by_phone( $phone );
		$is_new  = ! $user_id;
		if ( $is_new ) {
			$user_id = self::register( $phone );
			if ( is_wp_error( $user_id ) ) {
				return self::error( 'ساخت حساب ناموفق بود. لطفاً دوباره تلاش کنید.' );
			}
		}
		return self::login_response( $user_id, $req->get_param( 'redirect' ), $is_new );
	}

	public static function rest_password( WP_REST_Request $req ) {
		if ( ! AIM_Settings::get( 'password_login' ) ) {
			return self::error( 'ورود با گذرواژه غیرفعال است.' );
		}
		$phone   = aim_normalize_phone( $req->get_param( 'phone' ) );
		$user_id = $phone ? aim_get_user_by_phone( $phone ) : 0;
		// Two counters: per number and client (5 misses), and per number alone (20 misses), so
		// guesses spread over many IP addresses are limited too. The client IP is REMOTE_ADDR only.
		$key     = 'aim_pw_fail_' . md5( $phone . '|' . aim_client_ip() );
		$key_all = 'aim_pw_fail_all_' . md5( $phone );
		if ( (int) get_transient( $key ) >= 5 || (int) get_transient( $key_all ) >= 20 ) {
			return self::error( 'تلاش‌های ناموفق زیاد بود. ۱۵ دقیقهٔ دیگر دوباره امتحان کنید یا با کد یک‌بار مصرف وارد شوید.' );
		}
		$user = $user_id ? get_userdata( $user_id ) : null;
		if ( ! $user || ! is_scalar( $req->get_param( 'password' ) ) || ! wp_check_password( (string) $req->get_param( 'password' ), $user->user_pass, $user_id ) ) {
			set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
			set_transient( $key_all, (int) get_transient( $key_all ) + 1, 15 * MINUTE_IN_SECONDS );
			return self::error( 'شماره یا گذرواژه اشتباه است.' );
		}
		delete_transient( $key );
		delete_transient( $key_all );
		return self::login_response( $user_id, $req->get_param( 'redirect' ), false );
	}

	/**
	 * One request at a time per number. The resend wait and hourly limits read the table before the
	 * new row is written, so parallel requests would all pass them and each send an SMS.
	 * A plain INSERT into the options table fails for every request but the first (unique option_name).
	 *
	 * @return true|WP_Error
	 */
	public static function send( $phone ) {
		global $wpdb;
		$lock = 'aim_otp_lock_' . md5( $phone );
		$busy = new WP_Error( 'aim_wait', 'لطفاً چند ثانیه صبر کنید و دوباره درخواست دهید.', [ 'wait' => 5 ] );
		if ( false === $wpdb->insert( $wpdb->options, [ 'option_name' => $lock, 'option_value' => (string) time(), 'autoload' => 'no' ] ) ) {
			$since = time() - (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", $lock ) );
			if ( $since < 30 ) {
				return $busy;
			}
			// A lock left by a request that died halfway is taken over.
			$wpdb->delete( $wpdb->options, [ 'option_name' => $lock ] );
			if ( false === $wpdb->insert( $wpdb->options, [ 'option_name' => $lock, 'option_value' => (string) time(), 'autoload' => 'no' ] ) ) {
				return $busy;
			}
		}
		try {
			return self::send_code( $phone );
		} finally {
			$wpdb->delete( $wpdb->options, [ 'option_name' => $lock ] );
		}
	}

	/** @return true|WP_Error */
	private static function send_code( $phone ) {
		global $wpdb;
		$table = AIM_Install::table( 'otp' );
		$s     = AIM_Settings::all();
		$now   = time();

		$last = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s ORDER BY id DESC LIMIT 1", $phone ) );
		if ( $last ) {
			$since = $now - strtotime( $last->created_at . ' UTC' );
			if ( $since < (int) $s['otp_resend_wait'] ) {
				$wait = (int) $s['otp_resend_wait'] - $since;
				return new WP_Error( 'aim_wait', 'لطفاً ' . aim_fa_digits( $wait ) . ' ثانیهٔ دیگر دوباره درخواست دهید.', [ 'wait' => $wait ] );
			}
		}

		$hour_ago = gmdate( 'Y-m-d H:i:s', $now - HOUR_IN_SECONDS );
		$by_phone = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE phone = %s AND created_at > %s", $phone, $hour_ago ) );
		$by_ip    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE ip = %s AND created_at > %s", aim_client_ip(), $hour_ago ) );
		if ( $by_phone >= (int) $s['otp_hourly_limit'] || $by_ip >= (int) $s['otp_hourly_limit'] * 3 ) {
			return new WP_Error( 'aim_limit', 'تعداد درخواست‌ها زیاد است. یک ساعت دیگر دوباره تلاش کنید.' );
		}
		// Site-wide brake against SMS pumping (many numbers from many IPs): codes per 24 hours.
		$day_cap = (int) apply_filters( 'aim_otp_daily_cap', 1000 );
		$by_day  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE created_at > %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $day_cap > 0 && $by_day >= $day_cap && ! aim_get_user_by_phone( $phone ) ) {
			if ( ! get_transient( 'aim_otp_cap_notice' ) ) {
				AIM_Activity::log( 'otp_daily_cap', 'سقف روزانه ارسال کد برای شماره‌های جدید تکمیل شد.', 'sms' );
				set_transient( 'aim_otp_cap_notice', 1, DAY_IN_SECONDS );
			}
			return new WP_Error( 'aim_limit', 'ارسال کد موقتاً متوقف شده است. کمی بعد دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.' );
		}

		$len  = max( 4, min( 8, (int) $s['otp_length'] ) );
		$code = (string) random_int( 10 ** ( $len - 1 ), ( 10 ** $len ) - 1 );

		$sent = AIM_SMS::send_pattern( $phone, 'otp', [ 'code' => $code ] );
		if ( is_wp_error( $sent ) ) {
			return new WP_Error( 'aim_sms', 'ارسال پیامک با خطا روبه‌رو شد. چند لحظهٔ دیگر دوباره تلاش کنید.' );
		}

		// Older codes for this number stop working once a new one is sent (verify() reads only the
		// newest row). Rows of the last hour are kept: the resend wait and hourly limits count them.
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE phone = %s AND created_at <= %s", $phone, $hour_ago ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->insert( $table, [
			'phone'      => $phone,
			'code_hash'  => wp_hash_password( $code ),
			'attempts'   => 0,
			'ip'         => aim_client_ip(),
			'created_at' => gmdate( 'Y-m-d H:i:s', $now ),
			'expires_at' => gmdate( 'Y-m-d H:i:s', $now + (int) $s['otp_ttl'] ),
		] );
		return true;
	}

	/** @return true|WP_Error */
	public static function verify( $phone, $code ) {
		global $wpdb;
		$table = AIM_Install::table( 'otp' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s ORDER BY id DESC LIMIT 1", $phone ) );

		if ( ! $row || strtotime( $row->expires_at . ' UTC' ) < time() ) {
			return new WP_Error( 'aim_expired', 'کد منقضی شده است. کد جدید دریافت کنید.' );
		}
		// A used or burned code is expired, not deleted, so the resend wait and hourly limits still see it.
		$burn = fn() => $wpdb->update( $table, [ 'expires_at' => gmdate( 'Y-m-d H:i:s', 0 ) ], [ 'id' => $row->id ] );
		// Count the guess before checking it, in one statement, so parallel requests cannot exceed the limit.
		$counted = $wpdb->query( $wpdb->prepare( "UPDATE $table SET attempts = attempts + 1 WHERE id = %d AND attempts < %d", $row->id, (int) AIM_Settings::get( 'otp_max_attempts' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $counted ) {
			$burn();
			return new WP_Error( 'aim_attempts', 'تعداد تلاش‌ها بیش از حد مجاز بود. کد جدید دریافت کنید.' );
		}
		if ( ! wp_check_password( $code, $row->code_hash ) ) {
			return new WP_Error( 'aim_wrong', 'کد وارد شده اشتباه است.' );
		}
		$burn();
		return true;
	}

	private static function register( $phone ) {
		$login = 'u' . $phone;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = 'u' . $phone . '_' . ( ++$i );
		}
		$user_id = wp_insert_user( [
			'user_login'   => $login,
			'user_pass'    => wp_generate_password( 32 ),
			'user_email'   => '',
			'display_name' => 'کاربر ' . aim_fa_digits( substr( $phone, -4 ) ),
			'role'         => get_option( 'default_role', 'customer' ) ?: 'customer',
		] );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		update_user_meta( $user_id, 'aim_phone', $phone );
		update_user_meta( $user_id, 'billing_phone', $phone );
		AIM_Activity::log( 'user_registered', 'ثبت‌نام کاربر جدید با شماره ' . aim_fa_digits( $phone ), 'user', $user_id, $user_id );
		return $user_id;
	}

	private static function login_response( $user_id, $redirect, $is_new ) {
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false, is_ssl() );
		AIM_Activity::log( 'login', 'ورود به حساب', 'user', $user_id, $user_id );

		$default  = aim_panel_url( 'user' );
		$redirect = is_string( $redirect ) ? $redirect : '';
		$target   = '' === trim( $redirect ) ? $default : wp_validate_redirect( (string) $redirect, $default );
		return rest_ensure_response( [
			'ok'       => true,
			'is_new'   => $is_new,
			'redirect' => $target,
			'message'  => $is_new ? 'حساب شما ساخته شد. خوش آمدید!' : 'خوش آمدید!',
		] );
	}

	private static function error( $message, $wait = null ) {
		return new WP_REST_Response( [ 'ok' => false, 'message' => $message, 'wait' => $wait ], 400 );
	}
}
