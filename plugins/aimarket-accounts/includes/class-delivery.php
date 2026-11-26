<?php
defined( 'ABSPATH' ) || exit;

/**
 * Delivers accounts when an order is paid.
 *
 * auto   → takes N available rows from inventory (row-locked by an atomic
 *          UPDATE … WHERE status='available'), assigns them to the order item,
 *          and notifies the customer by SMS + email.
 * manual → the customer gives an activation email at checkout; the order
 *          waits in "processing" until an admin delivers it from the panel.
 *
 * Order item meta:
 *   _aim_delivered   number of accounts assigned
 *   _aim_shortage    accounts still owed when inventory ran out
 */
class AIM_Delivery {
	private static $locked_orders = [];

	private static function with_order_lock( $order_id, $callback ) {
		global $wpdb;
		$order_id = absint( $order_id );
		if ( isset( self::$locked_orders[ $order_id ] ) ) {
			return $callback();
		}
		$lock = 'aim_deliver_' . md5( $wpdb->prefix . ':' . $order_id );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return new WP_Error( 'aim_delivery_busy', 'تحویل در حال انجام است؛ دوباره تلاش کنید.' );
		}
		self::$locked_orders[ $order_id ] = true;
		try {
			return $callback();
		} finally {
			unset( self::$locked_orders[ $order_id ] );
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	private static function assigned_count( $item_id ) {
		global $wpdb;
		$table = AIM_Inventory::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE order_item_id = %d AND status = 'assigned'", $item_id ) );
	}

	public static function init() {
		$on = AIM_Settings::get( 'delivery_on_status' ) === 'completed' ? 'completed' : 'processing';
		// A completed order is always delivered (an admin may move on-hold → completed directly, skipping processing).
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'deliver_order' ], 20 );
		// «After manual confirmation» must not deliver as soon as the gateway reports payment.
		if ( 'processing' === $on ) {
			add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'deliver_order' ], 20 );
			add_action( 'woocommerce_payment_complete', [ __CLASS__, 'deliver_order' ], 20 );
		}

		// Digital products: skip shipping, require less at checkout.
		add_filter( 'woocommerce_cart_needs_shipping_address', [ __CLASS__, 'cart_needs_shipping' ] );
		add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'checkout_fields' ] );
		add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'save_checkout_email' ], 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', [ __CLASS__, 'require_login' ], 10, 2 );
		// Same rules for the Store API (block checkout / REST): no guest purchases of accounts.
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ __CLASS__, 'store_api_guard' ], 10, 2 );
		// Refuse cart quantities above what can be delivered.
		add_filter( 'woocommerce_update_cart_validation', [ __CLASS__, 'validate_cart_qty' ], 10, 4 );

		// Show delivered accounts on WooCommerce's own order screens/emails as a fallback.
		add_action( 'woocommerce_order_details_after_order_table', [ __CLASS__, 'order_details_notice' ] );
	}

	public static function deliver_order( $order_id ) {
		return self::with_order_lock( $order_id, static function () use ( $order_id ) {
			return self::deliver_locked( $order_id );
		} );
	}

	private static function deliver_locked( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ( ! $order->is_paid() && ! in_array( $order->get_status(), [ 'processing', 'completed' ], true ) ) ) {
			return;
		}

		// Accounts are shown in the customer's panel; an order without an account can't receive them.
		if ( ! $order->get_customer_id() ) {
			if ( ! $order->get_meta( '_aim_guest_hold' ) ) {
				$order->update_meta_data( '_aim_guest_hold', 1 );
				$order->save();
				$order->add_order_note( 'این سفارش بدون حساب کاربری ثبت شده و اکانتی تحویل نشد. پس از ثبت مشتری، از پنل «تحویل مجدد» بزنید.' );
			}
			return;
		}

		$delivered_now = [];
		$still_owed    = 0;
		$has_manual    = false;

		foreach ( $order->get_items() as $item_id => $item ) {
			/** @var WC_Order_Item_Product $item */
			$pid  = $item->get_product_id();
			$vid  = $item->get_variation_id();
			$mode = AIM_Inventory::delivery_mode( $pid );
			if ( ! $mode ) {
				continue;
			}
			if ( 'manual' === $mode ) {
				$has_manual = $has_manual || self::assigned_count( $item_id ) < (int) $item->get_quantity();
				continue;
			}

			$assigned = self::assigned_count( $item_id );
			$need = (int) $item->get_quantity() - $assigned;
			if ( $need <= 0 ) {
				$item->update_meta_data( '_aim_delivered', $assigned );
				$item->update_meta_data( '_aim_shortage', 0 );
				$item->save();
				continue;
			}
			$ids = self::claim( $pid, $vid, $need, $order, $item_id );
			if ( $ids ) {
				$item->update_meta_data( '_aim_delivered', $assigned + count( $ids ) );
				$delivered_now = array_merge( $delivered_now, $ids );
			}
			$short = $need - count( $ids );
			$item->update_meta_data( '_aim_shortage', max( 0, $short ) );
			$item->save();
			$still_owed += max( 0, $short );
			AIM_Inventory::sync_stock( $pid, $vid );
		}

		if ( $delivered_now ) {
			$order->add_order_note( aim_fa_digits( sprintf( '%d اکانت به‌صورت خودکار تحویل شد.', count( $delivered_now ) ) ) );
			AIM_Activity::log( 'order_delivered', aim_fa_digits( sprintf( 'تحویل خودکار %d اکانت برای سفارش #%d', count( $delivered_now ), $order->get_id() ) ), 'order', $order->get_id(), 0 );
			self::notify_customer( $order, $delivered_now );
		}

		// Payment and status hooks can both run for one order; report a shortage once per amount.
		if ( $still_owed && (int) $order->get_meta( '_aim_shortage_reported' ) !== $still_owed ) {
			$order->update_meta_data( '_aim_shortage_reported', $still_owed );
			$order->save();
			$order->add_order_note( aim_fa_digits( sprintf( 'موجودی کافی نبود؛ %d اکانت هنوز تحویل نشده است.', $still_owed ) ) );
			AIM_Activity::log( 'delivery_shortage', aim_fa_digits( sprintf( 'کمبود موجودی: %d اکانت برای سفارش #%d', $still_owed, $order->get_id() ) ), 'order', $order->get_id(), 0 );
			self::notify_admin_shortage( $order, $still_owed );
		}

		if ( ! $still_owed && ! $has_manual && $delivered_now && AIM_Settings::get( 'auto_complete_order' ) && 'completed' !== $order->get_status() ) {
			$order->update_status( 'completed', 'همهٔ اکانت‌ها تحویل شد.' );
		}
	}

	/**
	 * Atomically reserve up to $qty available accounts. Each UPDATE only
	 * succeeds while the row is still 'available', so two simultaneous orders
	 * can never receive the same account.
	 *
	 * @return int[] Claimed account ids.
	 */
	public static function claim( $pid, $vid, $qty, WC_Order $order, $item_id ) {
		$result = self::with_order_lock( $order->get_id(), static function () use ( $pid, $vid, $qty, $order, $item_id ) {
			$item = $order->get_item( $item_id );
			if ( ! $item instanceof WC_Order_Item_Product ) {
				return [];
			}
			$qty = max( 0, min( $qty, $item->get_quantity() - self::assigned_count( $item_id ) ) );
			return self::claim_locked( $pid, $vid, $qty, $order, $item_id );
		} );
		return is_wp_error( $result ) ? [] : $result;
	}

	private static function claim_locked( $pid, $vid, $qty, WC_Order $order, $item_id ) {
		global $wpdb;
		$table   = AIM_Inventory::table();
		$claimed = [];
		$tries   = 0;

		while ( count( $claimed ) < $qty && $tries++ < $qty * 4 ) {
			$candidates = $wpdb->get_col( $wpdb->prepare(
				"SELECT id FROM $table WHERE product_id = %d AND variation_id = %d AND status = 'available' ORDER BY id ASC LIMIT %d",
				$pid,
				$vid,
				$qty - count( $claimed )
			) );
			if ( ! $candidates ) {
				break;
			}
			foreach ( $candidates as $id ) {
				$now      = current_time( 'mysql', true );
				$duration = (int) $wpdb->get_var( $wpdb->prepare( "SELECT duration_days FROM $table WHERE id = %d", $id ) );
				$expires  = $duration ? "'" . gmdate( 'Y-m-d H:i:s', time() + $duration * DAY_IN_SECONDS ) . "'" : 'NULL';
				$updated  = $wpdb->query( $wpdb->prepare(
					"UPDATE $table SET status = 'assigned', order_id = %d, order_item_id = %d, user_id = %d, assigned_at = %s, expires_at = $expires WHERE id = %d AND status = 'available'", // $expires is a generated date or NULL.
					$order->get_id(),
					$item_id,
					$order->get_customer_id(),
					$now,
					$id
				) );
				if ( 1 === $updated ) {
					$claimed[] = (int) $id;
				}
			}
		}
		return $claimed;
	}

	/** Admin delivers a manual-mode item by typing the account details. */
	public static function deliver_manual( WC_Order $order, $item_id, $secret ) {
		return self::with_order_lock( $order->get_id(), static function () use ( $order, $item_id, $secret ) {
			return self::deliver_manual_locked( wc_get_order( $order->get_id() ), $item_id, $secret );
		} );
	}

	private static function deliver_manual_locked( WC_Order $order, $item_id, $secret ) {
		global $wpdb;
		$item = $order->get_item( $item_id );
		if ( ! $item instanceof WC_Order_Item_Product || self::assigned_count( $item_id ) >= $item->get_quantity() ) {
			return new WP_Error( 'aim_item', 'آیتم سفارش پیدا نشد.' );
		}
		$pid      = $item->get_product_id();
		$vid      = $item->get_variation_id();
		$duration = AIM_Inventory::duration_for( $pid, $vid );
		$now      = current_time( 'mysql', true );
		$wpdb->insert( AIM_Inventory::table(), [
			'product_id'    => $pid,
			'variation_id'  => $vid,
			'secret'        => AIM_Crypto::encrypt( $secret ),
			'note'          => 'تحویل دستی',
			'status'        => 'assigned',
			'order_id'      => $order->get_id(),
			'order_item_id' => $item_id,
			'user_id'       => $order->get_customer_id(),
			'duration_days' => $duration,
			'created_by'    => get_current_user_id(),
			'created_at'    => $now,
			'assigned_at'   => $now,
			'expires_at'    => $duration ? gmdate( 'Y-m-d H:i:s', time() + $duration * DAY_IN_SECONDS ) : null,
		] );
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new WP_Error( 'aim_insert', 'ذخیرهٔ اکانت انجام نشد؛ دوباره تلاش کنید.' );
		}
		$item->update_meta_data( '_aim_delivered', self::assigned_count( $item_id ) );
		$item->save();
		$order->add_order_note( 'اکانت به‌صورت دستی تحویل شد.' );
		AIM_Activity::log( 'order_delivered_manual', aim_fa_digits( sprintf( 'تحویل دستی اکانت برای سفارش #%d', $order->get_id() ) ), 'order', $order->get_id() );
		self::notify_customer( $order, [ $id ] );
		self::maybe_complete( $order );
		return $id;
	}

	/** Retry delivery for items that ran short (after new stock is added). */
	public static function retry( WC_Order $order ) {
		return self::with_order_lock( $order->get_id(), static function () use ( $order ) {
			self::deliver_locked( $order->get_id() );
			self::maybe_complete( $order );
		} );
	}

	private static function maybe_complete( WC_Order $order ) {
		$order = wc_get_order( $order->get_id() );
		foreach ( $order->get_items() as $item ) {
			if ( AIM_Inventory::delivery_mode( $item->get_product_id() ) && self::assigned_count( $item->get_id() ) < (int) $item->get_quantity() ) {
				return;
			}
		}
		if ( AIM_Settings::get( 'auto_complete_order' ) && 'completed' !== $order->get_status() ) {
			$order->update_status( 'completed', 'همهٔ اکانت‌ها تحویل شد.' );
		}
	}

	/** Per-item delivery state for the admin order view. */
	public static function item_state( WC_Order_Item $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return [ 'mode' => '', 'delivered' => 0, 'qty' => 0, 'owed' => 0 ];
		}
		$mode      = AIM_Inventory::delivery_mode( $item->get_product_id() );
		$delivered = self::assigned_count( $item->get_id() );
		$qty       = (int) $item->get_quantity();
		return [
			'mode'      => $mode,
			'delivered' => $delivered,
			'qty'       => $qty,
			'owed'      => max( 0, $qty - $delivered ),
		];
	}

	private static function notify_customer( WC_Order $order, array $ids ) {
		$phone = aim_normalize_phone( $order->get_billing_phone() ) ?: aim_user_phone( $order->get_customer_id() );
		$url   = aim_panel_url( 'user', 'accounts' );
		if ( $phone ) {
			AIM_SMS::send_pattern( $phone, 'delivery', [
				'order' => (string) $order->get_order_number(),
				'count' => (string) count( $ids ),
			] );
		}
		$email = $order->get_billing_email();
		if ( $email && is_email( $email ) ) {
			$shop = AIM_Settings::get( 'shop_title' );
			$body = sprintf(
				"سلام %s،\n\nاکانت سفارش #%s آماده است. برای دیدن اطلاعات ورود به «اکانت‌های من» بروید:\n%s\n\nبرای امنیت بیشتر، اطلاعات اکانت در ایمیل فرستاده نمی‌شود.\n\n%s",
				$order->get_billing_first_name() ?: 'دوست عزیز',
				$order->get_order_number(),
				$url,
				$shop
			);
			aim_mail( $email, sprintf( '[%s] اکانت شما تحویل شد — سفارش #%s', $shop, $order->get_order_number() ), 'اکانت شما آماده است', $body );
		}
	}

	private static function notify_admin_shortage( WC_Order $order, $owed ) {
		$to = AIM_Settings::get( 'support_email' ) ?: get_option( 'admin_email' );
		aim_mail(
			$to,
			sprintf( 'کمبود موجودی اکانت — سفارش #%s', $order->get_order_number() ),
			'کمبود موجودی اکانت',
			sprintf( "%d اکانت برای سفارش #%s تحویل نشد چون موجودی تمام شده بود.\nبعد از افزودن موجودی، از پنل سفارش روی «تحویل مجدد» بزنید:\n%s", $owed, $order->get_order_number(), aim_panel_url( 'admin', 'orders', [ 'order' => $order->get_id() ] ) )
		);
	}

	public static function cart_needs_shipping( $needs ) {
		if ( ! WC()->cart ) {
			return $needs;
		}
		foreach ( WC()->cart->get_cart() as $line ) {
			if ( ! AIM_Inventory::is_account_product( $line['product_id'] ) ) {
				return $needs;
			}
		}
		return false;
	}

	/** Digital-only carts: phone + name + email; plus activation email when a manual product is in the cart. */
	public static function checkout_fields( $fields ) {
		if ( ! WC()->cart ) {
			return $fields;
		}
		$all_digital = true;
		$has_manual  = false;
		foreach ( WC()->cart->get_cart() as $line ) {
			$mode        = AIM_Inventory::delivery_mode( $line['product_id'] );
			$all_digital = $all_digital && (bool) $mode;
			$has_manual  = $has_manual || 'manual' === $mode;
		}
		if ( $all_digital ) {
			foreach ( [ 'billing_company', 'billing_country', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode' ] as $k ) {
				unset( $fields['billing'][ $k ] );
			}
			if ( isset( $fields['billing']['billing_phone'] ) ) {
				$fields['billing']['billing_phone']['required'] = true;
				$phone = is_user_logged_in() ? aim_user_phone( get_current_user_id() ) : '';
				if ( $phone ) {
					$fields['billing']['billing_phone']['default'] = $phone;
				}
			}
		}
		if ( $has_manual ) {
			$fields['order']['aim_activation_email'] = [
				'type'        => 'email',
				'label'       => 'ایمیلی که اشتراک روی آن فعال شود',
				'placeholder' => 'example@gmail.com',
				'required'    => true,
				'class'       => [ 'form-row-wide' ],
				'priority'    => 5,
				'description' => 'برخی سرویس‌ها روی ایمیل خودتان فعال می‌شوند. ایمیل را دقیق وارد کنید.',
			];
		}
		return $fields;
	}

	/** Accounts are delivered to the customer's panel, so guests can't buy them. */
	public static function require_login( $data, WP_Error $errors ) {
		if ( ! WC()->cart ) {
			return;
		}
		$has_account = false;
		$has_manual  = false;
		foreach ( WC()->cart->get_cart() as $line ) {
			$mode        = AIM_Inventory::delivery_mode( $line['product_id'] );
			$has_account = $has_account || (bool) $mode;
			$has_manual  = $has_manual || 'manual' === $mode;
		}
		if ( $has_account && ! is_user_logged_in() ) {
			$errors->add( 'aim_login', 'برای خرید اکانت ابتدا با شمارهٔ موبایل وارد شوید.' );
		}
		if ( $has_manual && ! empty( $data['aim_activation_email'] ) && ! is_email( $data['aim_activation_email'] ) ) {
			$errors->add( 'aim_activation_email', 'ایمیل فعال‌سازی معتبر نیست. نمونهٔ درست: example@gmail.com' );
		}
	}

	/** Store API checkout: refuse guests when the cart has account products. */
	public static function store_api_guard( $order, $request ) {
		if ( is_user_logged_in() ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			if ( AIM_Inventory::is_account_product( $item->get_product_id() ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'aim_login_required', 'برای خرید اکانت ابتدا با شمارهٔ موبایل وارد شوید.', 401 );
			}
		}
	}

	/** Cart quantity updates may not exceed the stock that can actually be delivered. */
	public static function validate_cart_qty( $passed, $key, $values, $qty ) {
		$product = $values['data'] ?? null;
		if ( $passed && $product instanceof WC_Product && $product->managing_stock() && ! $product->backorders_allowed() && $qty > (int) $product->get_stock_quantity() ) {
			wc_add_notice( sprintf( 'از «%s» فقط %s عدد موجود است.', $product->get_name(), aim_fa_digits( max( 0, (int) $product->get_stock_quantity() ) ) ), 'error' );
			return false;
		}
		return $passed;
	}

	public static function save_checkout_email( WC_Order $order, $data ) {
		if ( ! empty( $data['aim_activation_email'] ) && is_email( $data['aim_activation_email'] ) ) {
			$order->update_meta_data( '_aim_activation_email', sanitize_email( $data['aim_activation_email'] ) );
		}
	}

	public static function order_details_notice( WC_Order $order ) {
		if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			if ( AIM_Inventory::delivery_mode( $item->get_product_id() ) ) {
				printf(
					'<p class="aim-order-accounts"><a class="button" href="%s">مشاهدهٔ اطلاعات اکانت‌ها در «اکانت‌های من»</a></p>',
					esc_url( aim_panel_url( 'user', 'accounts' ) )
				);
				return;
			}
		}
	}
}
