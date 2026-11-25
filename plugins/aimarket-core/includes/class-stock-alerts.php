<?php
defined( 'ABSPATH' ) || exit;

/**
 * "Tell me when it's back / on sale": a signed-in customer subscribes on the
 * product page; when the product comes back in stock (or goes on sale) every
 * pending subscriber gets one SMS and the request is marked sent.
 *
 * Requests are stored per parent product in {prefix}aim_alerts.
 *
 *   POST /alerts/subscribe         { product_id, type: stock|sale }   (customer)
 *   POST /alerts/send              { product_id, type }               (admin, sends now)
 *   POST /alerts/(id)/delete                                          (admin)
 */
class AIM_Stock_Alerts {

	const TYPES = [ 'stock' => 'اعلان موجودی', 'sale' => 'اعلان تخفیف' ];

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'woocommerce_product_set_stock_status', [ __CLASS__, 'stock_changed' ], 10, 2 );
		add_action( 'woocommerce_variation_set_stock_status', [ __CLASS__, 'stock_changed' ], 10, 2 );
		add_action( 'woocommerce_after_product_object_save', [ __CLASS__, 'product_saved' ] );
		add_action( 'aim_send_alerts', [ __CLASS__, 'send' ], 10, 2 );
	}

	public static function enabled() {
		return (bool) AIM_Settings::get( 'alerts_enabled' );
	}

	public static function routes() {
		$admin = fn() => current_user_can( 'manage_woocommerce' );
		register_rest_route( AIM_Admin_API::NS, '/alerts/subscribe', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'rest_subscribe' ], 'permission_callback' => '__return_true' ] );
		register_rest_route( AIM_Admin_API::NS, '/alerts/send', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'rest_send' ], 'permission_callback' => $admin ] );
		register_rest_route( AIM_Admin_API::NS, '/alerts/(?P<id>\d+)/delete', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'rest_delete' ], 'permission_callback' => $admin ] );
	}

	// ---- Customer ----

	public static function rest_subscribe( WP_REST_Request $req ) {
		if ( ! self::enabled() ) {
			return self::fail( 'اعلان موجودی فعلاً غیرفعال است.' );
		}
		$raw        = $req->get_param( 'product_id' );
		$product_id = is_scalar( $raw ) ? absint( aim_en_digits( (string) $raw ) ) : 0;
		// Requests are kept per parent product (the triggers and the admin list work on parents).
		$product_id = wp_get_post_parent_id( $product_id ) ?: $product_id;
		if ( ! is_user_logged_in() ) {
			$back = 'publish' === get_post_status( $product_id ) ? get_permalink( $product_id ) : home_url( '/' );
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'برای ثبت اعلان ابتدا وارد شوید.', 'login' => aim_login_url( $back ) ], 401 );
		}
		$type    = (string) $req->get_param( 'type' );
		$product = wc_get_product( $product_id );
		if ( ! isset( self::TYPES[ $type ] ) || ! $product || 'publish' !== $product->get_status() ) {
			return self::fail( 'محصول نامعتبر است.' );
		}
		if ( 'stock' === $type && $product->is_in_stock() ) {
			return self::fail( 'این محصول همین حالا موجود است.' );
		}
		if ( 'sale' === $type && $product->is_on_sale() ) {
			return self::fail( 'این محصول همین حالا تخفیف دارد.' );
		}
		$user  = get_current_user_id();
		// Fall back to the billing phone when the account phone is missing or not a valid number.
		$phone = aim_normalize_phone( aim_user_phone( $user ) ) ?: aim_normalize_phone( get_user_meta( $user, 'billing_phone', true ) );
		if ( ! $phone ) {
			return self::fail( 'شمارهٔ موبایل در حساب شما ثبت نشده است.' );
		}

		global $wpdb;
		$table  = AIM_Install::table( 'alerts' );
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE product_id = %d AND user_id = %d AND type = %s AND status = 'pending'", $product->get_id(), $user, $type ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $exists ) {
			return rest_ensure_response( [ 'ok' => true, 'message' => 'قبلاً ثبت کرده‌اید؛ پیامک اطلاع‌رسانی برایتان ارسال می‌شود.' ] );
		}
		$wpdb->insert( $table, [
			'product_id' => $product->get_id(),
			'user_id'    => $user,
			'phone'      => $phone,
			'type'       => $type,
			'status'     => 'pending',
			'created_at' => current_time( 'mysql', true ),
		] );
		$msg = 'stock' === $type ? 'ثبت شد؛ به محض موجود شدن، پیامک می‌گیرید.' : 'ثبت شد؛ وقتی تخفیف بخورد، پیامک می‌گیرید.';
		return rest_ensure_response( [ 'ok' => true, 'message' => $msg ] );
	}

	/** Whether the current user already waits for $type on $product_id (for the button state). */
	public static function is_subscribed( $product_id, $type ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		global $wpdb;
		$table = AIM_Install::table( 'alerts' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $table WHERE product_id = %d AND user_id = %d AND type = %s AND status = 'pending'", $product_id, get_current_user_id(), $type ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// ---- Triggers ----

	public static function stock_changed( $id, $status ) {
		if ( 'instock' === $status ) {
			$parent = wp_get_post_parent_id( $id ) ?: $id;
			self::queue( $parent, 'stock' );
			self::queue( $parent, 'sale' ); // Sale alerts wait until the product can be bought.
		}
	}

	public static function product_saved( $product ) {
		if ( $product instanceof WC_Product && $product->is_on_sale( 'edit' ) ) {
			self::queue( $product->get_parent_id() ?: $product->get_id(), 'sale' );
		}
	}

	/** Send in the background (Action Scheduler) so a stock or price change stays fast. */
	private static function queue( $product_id, $type ) {
		if ( ! self::enabled() || ! self::pending_count( $product_id, $type ) ) {
			return;
		}
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			if ( ! as_has_scheduled_action( 'aim_send_alerts', [ $product_id, $type ], 'aimarket' ) ) {
				as_enqueue_async_action( 'aim_send_alerts', [ $product_id, $type ], 'aimarket' );
			}
		} else {
			self::send( $product_id, $type );
		}
	}

	/** Last gateway error of send(), for the admin "send now" reply. */
	private static $last_error = '';

	/** Whether $type's condition holds: in stock, or (for sale) on sale and buyable. */
	private static function ready( $product, $type ) {
		return $product && $product->is_in_stock() && ( 'stock' === $type || $product->is_on_sale() );
	}

	/** SMS every pending subscriber of $product_id once the condition still holds. Returns the number sent. */
	public static function send( $product_id, $type ) {
		self::$last_error = '';
		$product = wc_get_product( $product_id );
		if ( ! isset( self::TYPES[ $type ] ) || ! self::ready( $product, $type ) ) {
			return 0;
		}
		global $wpdb;
		$table = AIM_Install::table( 'alerts' );
		$sent  = 0;
		// Batches of 500 until nobody is left (or the gateway fails); claimed rows drop out of the next batch.
		for ( $batch = 0; $batch < 40 && '' === self::$last_error; $batch++ ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, phone FROM $table WHERE product_id = %d AND type = %s AND status = 'pending' LIMIT 500", $product_id, $type ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				// Claim the request before sending, so an overlapping run cannot text the same subscriber twice.
				$claimed = $wpdb->update( $table, [ 'status' => 'sent', 'sent_at' => current_time( 'mysql', true ) ], [ 'id' => $row->id, 'status' => 'pending' ] );
				if ( ! $claimed ) {
					continue;
				}
				$res = AIM_SMS::send_pattern( $row->phone, 'alert_' . $type, [ 'product' => $product->get_name() ] );
				if ( is_wp_error( $res ) ) {
					$wpdb->update( $table, [ 'status' => 'pending' ], [ 'id' => $row->id ] ); // Not sent: keep it pending.
					self::$last_error = $res->get_error_message();
					AIM_Activity::log( 'alert_failed', 'ارسال اعلان ناموفق: ' . $res->get_error_message(), 'product', $product_id );
					break; // Gateway problem: leave the rest pending for the next trigger.
				}
				$sent++;
			}
		}
		if ( $sent ) {
			AIM_Activity::log( 'alerts_sent', sprintf( '%s برای «%s» به %s نفر ارسال شد', self::TYPES[ $type ], $product->get_name(), aim_fa_digits( $sent ) ), 'product', $product_id );
		}
		return $sent;
	}

	// ---- Admin ----

	public static function rest_send( WP_REST_Request $req ) {
		$type = (string) $req->get_param( 'type' );
		$pid  = absint( $req->get_param( 'product_id' ) );
		$pid  = wp_get_post_parent_id( $pid ) ?: $pid; // Requests are stored on the parent product.
		if ( ! isset( self::TYPES[ $type ] ) || ! wc_get_product( $pid ) ) {
			return self::fail( 'محصول نامعتبر است.' );
		}
		$product = wc_get_product( $pid );
		if ( ! $product->is_in_stock() ) {
			return self::fail( 'محصول هنوز ناموجود است؛ پس از شارژ انبار خودکار ارسال می‌شود.' );
		}
		if ( 'sale' === $type && ! $product->is_on_sale() ) {
			return self::fail( 'محصول هنوز تخفیف ندارد؛ پس از تعیین قیمت ویژه خودکار ارسال می‌شود.' );
		}
		$sent = self::send( $pid, $type );
		if ( self::$last_error ) {
			$done = $sent ? sprintf( 'برای %s نفر ارسال شد، اما ', aim_fa_digits( $sent ) ) : '';
			return self::fail( $done . 'ارسال پیامک ناموفق بود: ' . self::$last_error );
		}
		return rest_ensure_response( [ 'ok' => true, 'message' => $sent ? sprintf( 'برای %s نفر ارسال شد.', aim_fa_digits( $sent ) ) : 'درخواست در انتظاری نبود.' ] );
	}

	public static function rest_delete( WP_REST_Request $req ) {
		global $wpdb;
		$deleted = $wpdb->delete( AIM_Install::table( 'alerts' ), [ 'id' => absint( $req['id'] ) ] );
		return $deleted ? rest_ensure_response( [ 'ok' => true, 'message' => 'درخواست حذف شد.' ] ) : self::fail( 'درخواست پیدا نشد.', 404 );
	}

	/** Pending requests, overall or for one product/type. */
	public static function pending_count( $product_id = 0, $type = '' ) {
		global $wpdb;
		$table = AIM_Install::table( 'alerts' );
		$where = "a.status = 'pending'";
		if ( $product_id ) {
			$where .= $wpdb->prepare( ' AND a.product_id = %d AND a.type = %s', $product_id, $type );
		}
		// Requests for deleted or trashed products are not waiting for anything.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table a INNER JOIN {$wpdb->posts} p ON p.ID = a.product_id AND p.post_status IN ('publish','private') WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Pending requests grouped by product and type, most requested first. */
	public static function summary() {
		global $wpdb;
		$table = AIM_Install::table( 'alerts' );
		return $wpdb->get_results( "SELECT product_id, type, COUNT(*) AS total, MAX(created_at) AS last_at FROM $table WHERE status = 'pending' GROUP BY product_id, type ORDER BY total DESC LIMIT 50" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Individual requests, newest first. */
	public static function requests( $status, $type, $limit, $offset ) {
		global $wpdb;
		$table = AIM_Install::table( 'alerts' );
		$where = '1=1';
		if ( in_array( $status, [ 'pending', 'sent' ], true ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}
		if ( isset( self::TYPES[ $type ] ) ) {
			$where .= $wpdb->prepare( ' AND type = %s', $type );
		}
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return [ $rows, $total ];
	}

	private static function fail( $message, $code = 400 ) {
		return new WP_REST_Response( [ 'ok' => false, 'message' => $message ], $code );
	}
}
