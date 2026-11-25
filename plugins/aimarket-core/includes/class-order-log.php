<?php
defined( 'ABSPATH' ) || exit;

/**
 * Writes «ثبت سفارش» into the panel's activity feed the moment an order is
 * placed, so the dashboard (فعالیت‌های اخیر) announces every new order even
 * before the admin opens the orders list.
 *
 * Covered paths, with one entry per order (a meta marker keeps them apart):
 *   - classic checkout      → woocommerce_new_order + woocommerce_checkout_order_processed
 *   - block / Store API     → woocommerce_store_api_checkout_order_processed
 *   - manual (admin) orders → woocommerce_new_order (draft orders are ignored)
 */
class AIM_Order_Log {

        public static function init() {
                add_action( 'woocommerce_new_order', [ __CLASS__, 'from_new_order' ], 20 );
                add_action( 'woocommerce_checkout_order_processed', [ __CLASS__, 'from_checkout' ], 20, 3 );
                add_action( 'woocommerce_store_api_checkout_order_processed', [ __CLASS__, 'from_object' ], 20 );
        }

        /** True when this order already has its activity entry. */
        private static function logged( WC_Order $order ) {
                if ( $order->get_meta( '_aim_new_logged' ) ) {
                        return true;
                }
                $order->update_meta_data( '_aim_new_logged', 1 );
                $order->save();
                return false;
        }

        public static function from_new_order( $order_id ) {
                $order = wc_get_order( $order_id );
                if ( $order instanceof WC_Order && 'checkout-draft' !== $order->get_status() ) {
                        self::write( $order );
                }
        }

        public static function from_checkout( $order_id, $posted_data, $order ) {
                if ( $order instanceof WC_Order ) {
                        self::write( $order );
                } else {
                        $found = wc_get_order( $order_id );
                        if ( $found instanceof WC_Order ) {
                                self::write( $found );
                        }
                }
        }

        public static function from_object( $order ) {
                if ( $order instanceof WC_Order ) {
                        self::write( $order );
                }
        }

        private static function write( WC_Order $order ) {
                if ( self::logged( $order ) ) {
                        return;
                }
                $items    = $order->get_items();
                $first    = $items ? reset( $items ) : null;
                $customer = trim( $order->get_formatted_billing_full_name() ) ?: aim_normalize_phone( $order->get_billing_phone() ) ?: 'مهمان';
                $message  = sprintf(
                        'ثبت سفارش #%s توسط %s — %s — %s',
                        $order->get_order_number(),
                        $customer,
                        $first ? $first->get_name() : 'بدون قلم',
                        aim_price( $order->get_total() )
                );
                AIM_Activity::log( 'order_new', aim_fa_digits( $message ), 'order', $order->get_id(), (int) $order->get_customer_id() );
        }
}
