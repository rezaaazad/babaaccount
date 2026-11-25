<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST endpoints behind the panel UI. Cookie auth + X-WP-Nonce (WordPress
 * core verifies the nonce); each route checks its own capability.
 */
class AIM_Admin_API {

        const NS = 'aimarket/v1';

        public static function init() {
                add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
        }

        public static function routes() {
                $admin = fn() => current_user_can( 'manage_woocommerce' );
                $owner = fn() => current_user_can( 'manage_options' );
                $user  = fn() => is_user_logged_in();

                $r = function ( $path, $cb, $perm ) {
                        register_rest_route( self::NS, $path, [ 'methods' => 'POST', 'callback' => $cb, 'permission_callback' => $perm ] );
                };

                $r( '/orders/(?P<id>\d+)/status', [ __CLASS__, 'orders_status' ], $admin );
                $r( '/users/add', [ __CLASS__, 'users_add' ], fn() => current_user_can( 'create_users' ) );
                // Per-user check: a shop manager must not attach their own number to an administrator (OTP login as that admin).
                $r( '/users/(?P<id>\d+)/phone', [ __CLASS__, 'users_phone' ], fn( $req ) => current_user_can( 'edit_user', (int) $req['id'] ) );
                $r( '/settings', [ __CLASS__, 'settings' ], $owner );
                $r( '/settings/menu', [ 'AIM_Panel_Menu', 'rest_save' ], $owner );
                $r( '/sms/test', [ __CLASS__, 'sms_test' ], $owner );

                $r( '/me/profile', [ __CLASS__, 'me_profile' ], $user );
        }

        private static function ok( $message, $extra = [] ) {
                return rest_ensure_response( [ 'ok' => true, 'message' => $message ] + $extra );
        }

        private static function fail( $message, $code = 400 ) {
                return new WP_REST_Response( [ 'ok' => false, 'message' => $message ], $code );
        }

        public static function orders_status( WP_REST_Request $req ) {
                $order  = wc_get_order( (int) $req['id'] );
                $status = preg_replace( '/^wc-/', '', sanitize_key( $req->get_param( 'status' ) ) );
                if ( ! $order instanceof WC_Order ) { // Also rejects refunds, which share the order ID space.
                        return self::fail( 'سفارش پیدا نشد.', 404 );
                }
                if ( ! array_key_exists( 'wc-' . $status, wc_get_order_statuses() ) || 'checkout-draft' === $status ) {
                        return self::fail( 'وضعیت نامعتبر است.' );
                }
                $order->update_status( $status, 'تغییر وضعیت از پنل مدیریت.' );
                AIM_Activity::log( 'order_status', aim_fa_digits( sprintf( 'تغییر وضعیت سفارش #%s به «%s»', $order->get_order_number(), aim_order_status_label( $status ) ) ), 'order', $order->get_id() );
                return self::ok( 'وضعیت سفارش به‌روز شد.' );
        }

        // ---- Users ----

        public static function users_add( WP_REST_Request $req ) {
                $phone = is_scalar( $req->get_param( 'phone' ) ) ? aim_normalize_phone( $req->get_param( 'phone' ) ) : '';
                $name  = is_scalar( $req->get_param( 'name' ) ) ? sanitize_text_field( (string) $req->get_param( 'name' ) ) : '';
                if ( ! $phone ) {
                        return self::fail( 'شمارهٔ موبایل معتبر نیست.' );
                }
                if ( aim_get_user_by_phone( $phone ) ) {
                        return self::fail( 'کاربری با این شماره وجود دارد.' );
                }
                // Same login scheme as OTP sign-up; the plain login may belong to an account whose number was changed.
                $login = 'u' . $phone;
                $i     = 1;
                while ( username_exists( $login ) ) {
                        $login = 'u' . $phone . '_' . ( ++$i );
                }
                $user_id = wp_insert_user( [
                        'user_login'   => $login,
                        'user_pass'    => wp_generate_password( 32 ),
                        'display_name' => $name ?: 'کاربر ' . aim_fa_digits( substr( $phone, -4 ) ),
                        'first_name'   => $name,
                        'role'         => get_role( 'customer' ) ? 'customer' : ( get_option( 'default_role' ) ?: 'subscriber' ), // 'customer' exists only with WooCommerce.
                ] );
                if ( is_wp_error( $user_id ) ) {
                        return self::fail( $user_id->get_error_message() );
                }
                update_user_meta( $user_id, 'aim_phone', $phone );
                update_user_meta( $user_id, 'billing_phone', $phone );
                AIM_Activity::log( 'user_created', 'افزودن کاربر ' . aim_fa_digits( $phone ), 'user', $user_id );
                return self::ok( 'کاربر اضافه شد.' );
        }

        /** Lets an admin attach a phone to an existing account (e.g. the original WordPress admin) so it can use OTP login. */
        public static function users_phone( WP_REST_Request $req ) {
                $user_id = (int) $req['id'];
                $phone   = is_scalar( $req->get_param( 'phone' ) ) ? aim_normalize_phone( $req->get_param( 'phone' ) ) : '';
                if ( ! get_userdata( $user_id ) ) {
                        return self::fail( 'کاربر پیدا نشد.', 404 );
                }
                if ( ! $phone ) {
                        return self::fail( 'شمارهٔ موبایل معتبر نیست.' );
                }
                $other = aim_get_user_by_phone( $phone );
                if ( $other && $other !== $user_id ) {
                        return self::fail( 'این شماره برای کاربر دیگری ثبت شده است.' );
                }
                $old = aim_user_phone( $user_id );
                if ( ! get_user_meta( $user_id, 'billing_phone', true ) || get_user_meta( $user_id, 'billing_phone', true ) === $old ) {
                        update_user_meta( $user_id, 'billing_phone', $phone ); // Keep the billing phone in step unless it was set separately.
                }
                update_user_meta( $user_id, 'aim_phone', $phone );
                AIM_Activity::log( 'user_phone', 'ثبت شمارهٔ موبایل برای کاربر #' . aim_fa_digits( $user_id ), 'user', $user_id );
                return self::ok( 'شماره ثبت شد. از این پس با کد یک‌بار مصرف هم می‌توانید وارد شوید.' );
        }

        // ---- Settings ----

        public static function settings( WP_REST_Request $req ) {
                $values = (array) $req->get_json_params();
                unset( $values['_wpnonce'] );
                // A null is "not sent": without this, validate() skips it and update() stores 0 (e.g. otp_ttl = 0).
                $values = array_filter( $values, fn( $value ) => null !== $value );
                // Every setting is a single value; arrays would crash validation (TypeError → 500).
                foreach ( $values as $value ) {
                        if ( ! is_scalar( $value ) ) {
                                return self::fail( 'مقدار یکی از تنظیمات نامعتبر است.' );
                        }
                }
                if ( isset( $values['sms_driver'] ) && ( ! is_string( $values['sms_driver'] ) || ! array_key_exists( $values['sms_driver'], AIM_SMS::providers() ) ) ) {
                        return self::fail( 'درگاه پیامک نامعتبر است.' );
                }
                if ( isset( $values['support_phone'] ) ) {
                        // Stored with English digits so tel: links built from it work.
                        $values['support_phone'] = aim_en_digits( (string) $values['support_phone'] );
                }
                $error = AIM_Settings::validate( $values );
                if ( $error ) {
                        return self::fail( $error );
                }
                AIM_Settings::update( $values );
                AIM_Activity::log( 'settings_saved', 'ذخیرهٔ تنظیمات', 'settings' );
                return self::ok( 'تنظیمات ذخیره شد.' );
        }

        public static function sms_test( WP_REST_Request $req ) {
                $phone = aim_normalize_phone( $req->get_param( 'phone' ) );
                if ( ! $phone ) {
                        return self::fail( 'شمارهٔ موبایل معتبر نیست.' );
                }
                $res = AIM_SMS::send_pattern( $phone, 'otp', [ 'code' => '12345' ] );
                return is_wp_error( $res ) ? self::fail( $res->get_error_message() ) : self::ok( 'پیامک آزمایشی ارسال شد.' );
        }

        // ---- Current user ----

        public static function me_profile( WP_REST_Request $req ) {
                $user_id  = get_current_user_id();
                $name_in  = $req->get_param( 'display_name' );
                $email_in = $req->get_param( 'email' );
                if ( ( null !== $name_in && ! is_scalar( $name_in ) ) || ( null !== $email_in && ! is_scalar( $email_in ) ) ) {
                        return self::fail( 'اطلاعات واردشده نامعتبر است.' ); // An array in place of a text field (crafted request).
                }
                $name = sanitize_text_field( (string) $name_in );
                $raw  = trim( aim_en_digits( (string) $email_in ) );
                $email   = sanitize_email( $raw );
                $data    = [ 'ID' => $user_id ];
                if ( null !== $req->get_param( 'display_name' ) && '' === $name ) {
                        return self::fail( 'نام را وارد کنید.' );
                }
                if ( $name ) {
                        $data['display_name'] = $name;
                        $data['first_name']   = $name;
                }
                // sanitize_email() silently drops invalid characters; reject instead of saving a different address.
                if ( '' !== $raw && ( ! is_email( $email ) || $email !== $raw ) ) {
                        return self::fail( 'ایمیل معتبر نیست. نمونهٔ درست: example@gmail.com' );
                }
                if ( $email ) {
                        $owner = email_exists( $email );
                        if ( $owner && (int) $owner !== $user_id ) {
                                return self::fail( 'این ایمیل برای حساب دیگری ثبت شده است.' );
                        }
                        $data['user_email'] = $email;
                } elseif ( null !== $req->get_param( 'email' ) ) {
                        $data['user_email'] = ''; // The field was cleared (accounts made by OTP have no email either).
                }
                $pass = (string) $req->get_param( 'password' );
                if ( '' !== $pass ) {
                        if ( mb_strlen( $pass ) < 8 ) {
                                return self::fail( 'گذرواژه باید دست‌کم ۸ کاراکتر باشد.' );
                        }
                        if ( $pass !== (string) $req->get_param( 'password2' ) ) {
                                return self::fail( 'تکرار گذرواژه یکسان نیست.' );
                        }
                        $data['user_pass'] = $pass;
                }
                $res = wp_update_user( $data );
                if ( is_wp_error( $res ) ) {
                        return self::fail( $res->get_error_message() );
                }
                if ( isset( $data['user_email'] ) ) {
                        update_user_meta( $user_id, 'billing_email', $data['user_email'] );
                }
                if ( '' !== $pass ) {
                        // Changing the password logs the user out; log them back in on this device and
                        // reload the page, since the new login cookie invalidates the page's REST nonce.
                        wp_set_auth_cookie( $user_id, true, is_ssl() );
                        return self::ok( 'پروفایل ذخیره شد.', [ 'redirect' => aim_panel_url( 'user', 'profile' ) ] );
                }
                return self::ok( 'پروفایل ذخیره شد.' );
        }
}
