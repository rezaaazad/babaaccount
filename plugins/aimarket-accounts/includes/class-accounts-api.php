<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST endpoints of the accounts module (namespace aimarket/v1, cookie auth +
 * X-WP-Nonce like the core panel): inventory, manual delivery, re-delivery,
 * and the customer's "show my account".
 */
class AIM_Accounts_API {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	public static function routes() {
		$admin = fn() => current_user_can( 'manage_woocommerce' );
		$user  = fn() => is_user_logged_in();
		$r     = function ( $path, $cb, $perm ) {
			register_rest_route( AIM_Admin_API::NS, $path, [ 'methods' => 'POST', 'callback' => $cb, 'permission_callback' => $perm ] );
		};
		$r( '/accounts/add', [ __CLASS__, 'accounts_add' ], $admin );
		$r( '/accounts/(?P<id>\d+)/reveal', [ __CLASS__, 'accounts_reveal' ], $admin );
		$r( '/accounts/(?P<id>\d+)/status', [ __CLASS__, 'accounts_status' ], $admin );
		$r( '/accounts/(?P<id>\d+)/delete', [ __CLASS__, 'accounts_delete' ], $admin );
		$r( '/orders/(?P<id>\d+)/deliver', [ __CLASS__, 'orders_deliver' ], $admin );
		$r( '/orders/(?P<id>\d+)/retry', [ __CLASS__, 'orders_retry' ], $admin );
		$r( '/me/accounts/(?P<id>\d+)/reveal', [ __CLASS__, 'me_reveal' ], $user );
	}

	private static function ok( $message, $extra = [] ) {
		return rest_ensure_response( [ 'ok' => true, 'message' => $message ] + $extra );
	}

	private static function fail( $message, $code = 400 ) {
		return new WP_REST_Response( [ 'ok' => false, 'message' => $message ], $code );
	}

	// ---- Accounts ----

	public static function accounts_add( WP_REST_Request $req ) {
		if ( ! preg_match( '/^(\d+):(\d+)$/', (string) $req->get_param( 'target' ), $m ) ) {
			return self::fail( 'محصول را انتخاب کنید.' );
		}
		[ , $pid, $vid ] = array_map( 'intval', $m );
		$product = wc_get_product( $pid );
		if ( ! $product || ! AIM_Inventory::is_account_product( $pid ) ) {
			return self::fail( 'این محصول به‌عنوان «اکانت» تنظیم نشده است.' );
		}
		if ( 'auto' !== AIM_Inventory::delivery_mode( $pid ) ) {
			return self::fail( 'تحویل این محصول دستی است و انبار اکانت ندارد؛ اکانت را از صفحهٔ سفارش تحویل دهید.' );
		}
		if ( $product->is_type( 'variable' ) ? ! in_array( $vid, array_map( 'intval', $product->get_children() ), true ) : 0 !== $vid ) {
			return self::fail( $product->is_type( 'variable' ) ? 'یکی از پلن‌های این محصول را انتخاب کنید.' : 'پلن انتخاب‌شده متعلق به این محصول نیست.' );
		}
		$text    = $req->get_param( 'secrets' );
		$secrets = is_string( $text ) ? AIM_Inventory::parse_bulk( $text, $req->get_param( 'mode' ) === 'block' ? 'block' : 'line' ) : [];
		if ( ! $secrets ) {
			return self::fail( 'اطلاعات هیچ اکانتی وارد نشده است.' );
		}
		if ( count( $secrets ) > 2000 ) {
			return self::fail( 'در هر بار حداکثر ۲۰۰۰ اکانت قابل افزودن است.' );
		}
		$parsed  = count( $secrets );
		$secrets = AIM_Inventory::without_duplicates( $pid, $vid, $secrets );
		$dupes   = $parsed - count( $secrets );
		if ( ! $secrets ) {
			return self::fail( 'همهٔ اکانت‌های واردشده تکراری‌اند و از قبل در انبار این محصول هستند.' );
		}
		$note  = $req->get_param( 'note' );
		$added = AIM_Inventory::add( $pid, $vid, $secrets, is_string( $note ) ? $note : '' );
		if ( ! $added ) {
			return self::fail( 'ذخیرهٔ اکانت‌ها انجام نشد؛ دوباره تلاش کنید.', 500 );
		}
		return self::ok( aim_fa_digits( $added ) . ' اکانت به انبار اضافه شد.' . ( $dupes ? ' ' . aim_fa_digits( $dupes ) . ' مورد تکراری نادیده گرفته شد.' : '' ) );
	}

	public static function accounts_reveal( WP_REST_Request $req ) {
		$row = AIM_Inventory::get( (int) $req['id'] );
		if ( ! $row ) {
			return self::fail( 'اکانت پیدا نشد.', 404 );
		}
		AIM_Activity::log( 'account_revealed', 'مشاهدهٔ اطلاعات اکانت #' . aim_fa_digits( $row->id ), 'account', $row->id );
		return self::ok( '', [ 'reveal' => AIM_Inventory::secret( $row ) ] );
	}

	public static function accounts_status( WP_REST_Request $req ) {
		$status = sanitize_key( $req->get_param( 'status' ) );
		$row    = AIM_Inventory::get( (int) $req['id'] );
		if ( ! $row ) {
			return self::fail( 'اکانت پیدا نشد.', 404 );
		}
		if ( 'assigned' === $row->status ) {
			return self::fail( 'وضعیت اکانت تحویل‌شده از اینجا تغییر نمی‌کند.' );
		}
		if ( ! in_array( $status, [ 'available', 'disabled' ], true ) ) {
			return self::fail( 'وضعیت انتخاب‌شده نامعتبر است.' );
		}
		AIM_Inventory::set_status( $row->id, $status );
		AIM_Activity::log( 'account_status', aim_fa_digits( sprintf( 'تغییر وضعیت اکانت #%d به %s', $row->id, AIM_Inventory::STATUSES[ $status ][0] ) ), 'account', $row->id );
		return self::ok( 'وضعیت اکانت تغییر کرد.' );
	}

	public static function accounts_delete( WP_REST_Request $req ) {
		$id  = (int) $req['id'];
		$row = AIM_Inventory::get( $id );
		if ( ! $row ) {
			return self::fail( 'اکانت پیدا نشد.', 404 );
		}
		if ( ! AIM_Inventory::delete( $id ) ) {
			return self::fail( 'اکانت تحویل‌شده قابل حذف نیست.' );
		}
		AIM_Activity::log( 'account_deleted', 'حذف اکانت #' . aim_fa_digits( $id ), 'account', $id );
		return self::ok( 'اکانت حذف شد.' );
	}

	// ---- Orders ----

	public static function orders_deliver( WP_REST_Request $req ) {
		$order  = wc_get_order( (int) $req['id'] );
		$secret = trim( (string) $req->get_param( 'secret' ) );
		if ( ! $order ) {
			return self::fail( 'سفارش پیدا نشد.', 404 );
		}
		if ( '' === $secret ) {
			return self::fail( 'اطلاعات اکانت را وارد کنید.' );
		}
		$item = $order->get_item( (int) $req->get_param( 'item_id' ) );
		if ( ! $item instanceof WC_Order_Item_Product || (int) $item->get_order_id() !== (int) $order->get_id() ) {
			return self::fail( 'این قلم متعلق به این سفارش نیست.' );
		}
		$state = AIM_Delivery::item_state( $item );
		if ( ! $state['mode'] ) {
			return self::fail( 'این قلم اکانت نیست.' );
		}
		if ( $state['owed'] < 1 ) {
			return self::fail( 'همهٔ اکانت‌های این قلم قبلاً تحویل شده است.' );
		}
		if ( ! $order->is_paid() ) {
			return self::fail( 'این سفارش هنوز پرداخت نشده است.' );
		}
		if ( ! $order->get_customer_id() ) {
			return self::fail( 'این سفارش به حساب کاربری متصل نیست و مشتری اکانت را در پنل خود نمی‌بیند. ابتدا سفارش را به حساب مشتری متصل کنید.' );
		}
		$res = AIM_Delivery::deliver_manual( $order, (int) $req->get_param( 'item_id' ), $secret );
		return is_wp_error( $res ) ? self::fail( $res->get_error_message() ) : self::ok( 'اکانت تحویل و به مشتری اطلاع داده شد.' );
	}

	public static function orders_retry( WP_REST_Request $req ) {
		$order = wc_get_order( (int) $req['id'] );
		if ( ! $order ) {
			return self::fail( 'سفارش پیدا نشد.', 404 );
		}
		$owed = 0;
		foreach ( $order->get_items() as $it ) {
			$st    = AIM_Delivery::item_state( $it );
			$owed += 'auto' === $st['mode'] ? $st['owed'] : 0;
		}
		if ( ! $owed ) {
			return self::fail( 'اکانت تحویل‌نشده‌ای (با تحویل خودکار) در این سفارش نیست.' );
		}
		if ( ! $order->is_paid() ) {
			return self::fail( 'این سفارش هنوز پرداخت نشده است.' );
		}
		if ( ! $order->get_customer_id() ) {
			return self::fail( 'این سفارش به حساب کاربری متصل نیست و مشتری اکانت را در پنل خود نمی‌بیند. ابتدا سفارش را به حساب مشتری متصل کنید.' );
		}
		AIM_Delivery::retry( $order );
		$left = 0;
		foreach ( wc_get_order( $order->get_id() )->get_items() as $it ) {
			$st    = AIM_Delivery::item_state( $it );
			$left += 'auto' === $st['mode'] ? $st['owed'] : 0;
		}
		if ( ! $left ) {
			return self::ok( 'همهٔ اکانت‌های مانده تحویل شد.' );
		}
		// Partial success still changed the order: report it as ok so the panel reloads the order view.
		return $left < $owed
			? self::ok( aim_fa_digits( $owed - $left ) . ' اکانت تحویل شد؛ ' . aim_fa_digits( $left ) . ' اکانت هنوز به‌خاطر کمبود موجودی مانده است.' )
			: self::fail( 'موجودی انبار کافی نیست؛ ' . aim_fa_digits( $left ) . ' اکانت هنوز تحویل نشده است. ابتدا به انبار اکانت اضافه کنید.' );
	}

	public static function me_reveal( WP_REST_Request $req ) {
		$row = AIM_Inventory::get( (int) $req['id'] );
		if ( ! $row || (int) $row->user_id !== get_current_user_id() || 'assigned' !== $row->status ) {
			return self::fail( 'اکانت پیدا نشد.', 404 );
		}
		AIM_Activity::log( 'account_revealed_customer', aim_fa_digits( sprintf( 'مشتری اطلاعات اکانت #%d را دید', $row->id ) ), 'account', (int) $row->id );
		return self::ok( '', [ 'reveal' => AIM_Inventory::secret( $row ) ] );
	}
}
