<?php
defined( 'ABSPATH' ) || exit;

/**
 * All plugin settings live in one option array. Defaults are defined here so
 * every reader gets a complete set of keys; modules add their keys with the
 * 'aim_settings_defaults' filter and their checks with 'aim_settings_validate'.
 */
class AIM_Settings {

        const OPTION = 'aim_settings';

        public static function defaults() {
                return apply_filters( 'aim_settings_defaults', [
                        // General.
                        'shop_title'            => get_bloginfo( 'name' ),
                        'support_phone'         => '',
                        'support_email'         => get_option( 'admin_email' ),
                        'response_time'         => 'هر روز از ۹ صبح تا ۱۲ شب',
                        'theme_mode'            => 'light', // light | dark | auto.

                        // OTP / login.
                        'otp_length'            => 5,
                        'otp_ttl'               => 120,  // Seconds a code stays valid.
                        'otp_resend_wait'       => 60,   // Seconds before a new code can be requested.
                        'otp_max_attempts'      => 5,    // Wrong guesses before a code is burned.
                        'otp_hourly_limit'      => 6,    // Codes per phone per hour.
                        'password_login'        => 1,

                        // SMS: every provider keeps its own credentials and
                        // pattern codes (sms_provider_* / sms_pattern_*),
                        // generated from AIM_SMS::providers() so switching
                        // providers never loses the other panel's settings.
                        'sms_driver'            => 'log', // log | kavenegar | smsir | ippanel | melipayamak.
                        'sms_provider_kavenegar_key'      => '',
                        'sms_provider_smsir_key'          => '',
                        'sms_provider_ippanel_key'        => '',
                        'sms_provider_ippanel_sender'     => '',
                        'sms_provider_melipayamak_username' => '',
                        'sms_provider_melipayamak_key'    => '',
                        // Pattern codes per provider per event: otp, alert_stock, alert_sale (core)
                        // + delivery, reminder (accounts module).
                        'sms_pattern_kavenegar_otp'         => '',
                        'sms_pattern_kavenegar_alert_stock' => '',
                        'sms_pattern_kavenegar_alert_sale'  => '',
                        'sms_pattern_kavenegar_delivery'    => '',
                        'sms_pattern_kavenegar_reminder'    => '',
                        'sms_pattern_smsir_otp'             => '',
                        'sms_pattern_smsir_alert_stock'     => '',
                        'sms_pattern_smsir_alert_sale'      => '',
                        'sms_pattern_smsir_delivery'        => '',
                        'sms_pattern_smsir_reminder'        => '',
                        'sms_pattern_ippanel_otp'           => '',
                        'sms_pattern_ippanel_alert_stock'   => '',
                        'sms_pattern_ippanel_alert_sale'    => '',
                        'sms_pattern_ippanel_delivery'      => '',
                        'sms_pattern_ippanel_reminder'      => '',
                        'sms_pattern_melipayamak_otp'         => '',
                        'sms_pattern_melipayamak_alert_stock' => '',
                        'sms_pattern_melipayamak_alert_sale'  => '',
                        'sms_pattern_melipayamak_delivery'    => '',
                        'sms_pattern_melipayamak_reminder'    => '',

                        // Customer features.
                        'alerts_enabled'        => 1,
                        'thankyou_coupon'       => '',
                        'thankyou_coupon_text'  => 'کد تخفیف خرید بعدی شما',

                        // Site appearance (theme + Elementor): header/footer template id, 0 = the theme's, -1 = built-in.
                        'el_header'             => 0,
                        'el_footer'             => 0,

                        // Optimisation (front end).
                        'opt_disable_emojis'    => 0,
                        'opt_disable_embeds'    => 0,
                        'opt_jquery_migrate'    => 0,
                        'opt_remove_ver'        => 0,

                        // Stock.
                        'low_stock_threshold'   => 3,
                ] );
        }

        public static function all() {
                $all = wp_parse_args( get_option( self::OPTION, [] ), self::defaults() );
                // The shop title IS the WordPress site name (Settings → General → Site Title): one value,
                // so the panel, emails, SMS, header and footer can never disagree.
                $all['shop_title'] = get_option( 'blogname' );
                return $all;
        }

        public static function get( $key ) {
                $all = self::all();
                return $all[ $key ] ?? null;
        }

        /** @return string Persian error message, or '' when the values are valid. */
        public static function validate( array $values ) {
                $ranges = [
                        'otp_length'          => [ 4, 8, 'تعداد ارقام کد' ],
                        'otp_ttl'             => [ 60, 900, 'اعتبار کد' ],
                        'otp_resend_wait'     => [ 30, 600, 'فاصلهٔ ارسال مجدد' ],
                        'otp_max_attempts'    => [ 3, 10, 'حداکثر تلاش اشتباه' ],
                        'otp_hourly_limit'    => [ 3, 20, 'حداکثر کد در ساعت' ],
                        'low_stock_threshold' => [ 0, 100, 'هشدار موجودی کم' ],
                ];
                foreach ( $ranges as $key => [ $min, $max, $label ] ) {
                        if ( isset( $values[ $key ] ) ) {
                                $v = aim_en_digits( (string) $values[ $key ] );
                                if ( ! ctype_digit( $v ) || (int) $v < $min || (int) $v > $max ) {
                                        return sprintf( '«%s» باید عددی بین %s و %s باشد.', $label, aim_fa_digits( $min ), aim_fa_digits( $max ) );
                                }
                        }
                }
                if ( ! empty( $values['support_email'] ) && ! is_email( $values['support_email'] ) ) {
                        return 'ایمیل پشتیبانی معتبر نیست.';
                }
                if ( isset( $values['theme_mode'] ) && ! in_array( $values['theme_mode'], [ 'light', 'dark', 'auto' ], true ) ) {
                        return 'حالت نمایش نامعتبر است.';
                }
                $error = (string) apply_filters( 'aim_settings_validate', '', $values );
                if ( '' !== $error ) {
                        return $error;
                }
                if ( ! empty( $values['thankyou_coupon'] ) && function_exists( 'wc_get_coupon_id_by_code' ) && ! wc_get_coupon_id_by_code( sanitize_text_field( $values['thankyou_coupon'] ) ) ) {
                        return 'کد تخفیف صفحهٔ تشکر در «کدهای تخفیف» پیدا نشد.';
                }
                return '';
        }

        public static function update( array $values ) {
                $defaults = self::defaults();
                $current  = self::all();
                foreach ( $values as $key => $value ) {
                        if ( ! array_key_exists( $key, $defaults ) ) {
                                continue;
                        }
                        $current[ $key ] = is_int( $defaults[ $key ] ) ? (int) aim_en_digits( (string) $value ) : sanitize_text_field( $value );
                }
                if ( isset( $values['shop_title'] ) && '' !== trim( (string) $values['shop_title'] ) ) {
                        update_option( 'blogname', sanitize_text_field( $values['shop_title'] ) );
                }
                unset( $current['shop_title'] ); // stored as blogname only (see all())
                update_option( self::OPTION, $current );
                return self::all();
        }
}
