<?php
defined( 'ABSPATH' ) || exit;

/**
 * SMS gateway. Every message goes through send_pattern() with a named event
 * (otp, alert_stock, alert_sale, delivery, reminder) and its variables; each
 * provider maps that to its own template API with its own credentials. The
 * provider registry (providers()) is the single source for the settings UI
 * AND the sender: per provider it lists exactly the fields that provider's
 * API needs — nothing shared, so switching providers never mixes settings.
 * The "log" provider keeps the last 50 messages in an option so the whole
 * flow can be tested without a panel.
 *
 * API specs verified against official docs (2025-11):
 *  - Kavenegar  https://kavenegar.com/rest.html            (verify/lookup, token ≤ 100 chars, no spaces, template approved in panel)
 *  - sms.ir     https://sms.ir/rest-api/                   (X-API-KEY, numeric templateId, parameter names must match the template, values ≤ 25 chars)
 *  - IPPanel    https://apidoc.ippanel.com/                (api2 host + apikey header, pattern code + sender line — the shape the official SDKs ship)
 *  - Melipayamak https://docs.melipayamak.com/             (rest.payamak-panel.com, username + API key as password, BaseServiceNumber with numeric bodyId)
 */
class AIM_SMS {

        /**
         * Provider registry — one entry per SMS panel. 'creds' keys map to the
         * settings keys sms_provider_{driver}_{cred}; the settings form shows
         * exactly these fields for the selected provider and nothing else.
         */
        public static function providers() {
                return [
                        'log'         => [
                                'label' => 'آزمایشی (بدون ارسال واقعی)',
                                'note'  => 'پیامک‌ها جایی ارسال نمی‌شوند و فقط در «گزارش پیامک» ثبت می‌شوند؛ برای تست کل مسیر بدون پنل پیامکی.',
                        ],
                        'kavenegar'   => [
                                'label'         => 'کاوه‌نگار',
                                'creds'         => [
                                        'key' => [ 'label' => 'کلید API', 'type' => 'password', 'help' => 'از پنل کاوه‌نگار ← تنظیمات ← کلیدهای API.' ],
                                ],
                                'pattern_label' => 'نام الگو',
                                'pattern_help'  => 'نام الگوی «اعتبارسنجی» که در پنل کاوه‌نگار ساخته و تأیید شده است؛ انگلیسی، بدون فاصله. متغیرها به‌ترتیب در token و token2 و token3 جای می‌گیرند و فاصله در آن‌ها مجاز نیست، پس با نیم‌فاصله جایگزین می‌شود.',
                                'docs'          => 'https://kavenegar.com/rest.html',
                        ],
                        'smsir'       => [
                                'label'         => 'ایده‌پردازان (sms.ir)',
                                'creds'         => [
                                        'key' => [ 'label' => 'کلید API', 'type' => 'password', 'help' => 'کلید X-API-KEY از پنل sms.ir ← توسعه‌دهندگان.' ],
                                ],
                                'pattern_label' => 'شناسهٔ قالب (عددی)',
                                'pattern_help'  => 'شناسهٔ عددی قالب ساخته‌شده در پنل sms.ir؛ نام متغیرها باید دقیقاً همان کلید داخل قالب باشد (بدون #) و هر مقدار حداکثر ۲۵ کاراکتر.',
                                'docs'          => 'https://sms.ir/rest-api/',
                        ],
                        'ippanel'     => [
                                'label'         => 'IPPanel (فراز اس‌ام‌اس)',
                                'creds'         => [
                                        'key'    => [ 'label' => 'کلید API', 'type' => 'password', 'help' => 'از پنل IPPanel ← مدیریت کاربران/API.' ],
                                        'sender' => [ 'label' => 'شمارهٔ فرستنده', 'type' => 'text', 'help' => 'خط اختصاصی یا خدماتی پنل، مثل 5000...' ],
                                ],
                                'pattern_label' => 'کد پترن',
                                'pattern_help'  => 'کد پترن تأییدشده در پنل IPPanel (رشته‌ای مثل t2cfmnyo0c)؛ نام متغیرها باید مطابق پترن ساخته‌شده باشد.',
                                'docs'          => 'https://apidoc.ippanel.com/',
                        ],
                        'melipayamak' => [
                                'label'         => 'ملی پیامک',
                                'creds'         => [
                                        'username' => [ 'label' => 'نام کاربری پنل', 'type' => 'text', 'help' => 'نام کاربری ورود به پنل ملی پیامک.' ],
                                        'key'      => [ 'label' => 'کلید API (رمز وب‌سرویس)', 'type' => 'password', 'help' => 'از پنل ملی پیامک ← تنظیمات ← وب‌سرویس؛ در درخواست‌ها به‌جای رمز عبور فرستاده می‌شود.' ],
                                ],
                                'pattern_label' => 'شناسهٔ الگو (BodyId)',
                                'pattern_help'  => 'شناسهٔ عددی الگوی خدماتی تأییدشده (بانک شماره) در پنل ملی پیامک؛ مقادیر متغیرها به‌ترتیب و با «;» از هم جدا می‌شوند.',
                                'docs'          => 'https://docs.melipayamak.com/',
                        ],
                ];
        }

        /** Pattern events and their Persian labels; modules add their own (delivery, reminder…). */
        public static function events() {
                return apply_filters( 'aim_sms_events', [
                        'otp'         => 'کد ورود',
                        'alert_stock' => 'اعلان موجودی',
                        'alert_sale'  => 'اعلان تخفیف',
                ] );
        }

        /** Settings key of one credential: sms_provider_{driver}_{cred}. */
        public static function cred_key( $driver, $cred ) {
                return 'sms_provider_' . $driver . '_' . $cred;
        }

        /** Settings key of one pattern: sms_pattern_{driver}_{event}. */
        public static function pattern_key( $driver, $event ) {
                return 'sms_pattern_' . $driver . '_' . $event;
        }

        /**
         * @param string $phone  09xxxxxxxxx.
         * @param string $event  otp | alert_stock | alert_sale | delivery | reminder.
         * @param array  $vars   Ordered name => value pairs.
         * @return true|WP_Error
         */
        public static function send_pattern( $phone, $event, array $vars ) {
                $s      = AIM_Settings::all();
                $driver = $s['sms_driver'];

                if ( 'log' === $driver ) {
                        return self::log( $phone, $event, $vars );
                }

                $provider = self::providers()[ $driver ] ?? null;
                if ( ! $provider ) {
                        return new WP_Error( 'aim_sms_config', 'درگاه پیامک شناخته‌شده نیست.' );
                }
                foreach ( $provider['creds'] as $cred => $meta ) {
                        if ( '' === (string) ( $s[ self::cred_key( $driver, $cred ) ] ?? '' ) ) {
                                return new WP_Error( 'aim_sms_config', 'تنظیمات پیامک کامل نیست: در درگاه «' . $provider['label'] . '» فیلد «' . $meta['label'] . '» خالی است.' );
                        }
                }
                $pattern = (string) ( $s[ self::pattern_key( $driver, $event ) ] ?? '' );
                if ( '' === $pattern ) {
                        return new WP_Error( 'aim_sms_config', sprintf( 'کد الگوی «%1$s» برای درگاه «%2$s» تنظیم نشده است.', self::events()[ $event ] ?? $event, $provider['label'] ) );
                }

                switch ( $driver ) {
                        case 'kavenegar':
                                $result = self::kavenegar( $s, $phone, $pattern, $vars );
                                break;
                        case 'smsir':
                                $result = self::smsir( $s, $phone, $pattern, $vars );
                                break;
                        case 'ippanel':
                                $result = self::ippanel( $s, $phone, $pattern, $vars );
                                break;
                        case 'melipayamak':
                                $result = self::melipayamak( $s, $phone, $pattern, $vars );
                                break;
                        default:
                                $result = self::log( $phone, $event, $vars );
                }

                if ( is_wp_error( $result ) ) {
                        AIM_Activity::log( 'sms_failed', 'ارسال پیامک ناموفق: ' . $result->get_error_message(), 'sms' );
                }
                return $result;
        }

        private static function log( $phone, $event, $vars ) {
                $log   = get_option( 'aim_sms_log', [] );
                array_unshift( $log, [
                        'phone' => $phone,
                        'event' => $event,
                        'vars'  => $vars,
                        'time'  => time(),
                ] );
                update_option( 'aim_sms_log', array_slice( $log, 0, 50 ), false );
                return true;
        }

        /**
         * Kavenegar verify/lookup: template NAME approved in the panel; values go
         * positionally into token/token2/token3 — spaces are not allowed, so they
         * become ZWNJ. No sender line: Kavenegar picks the line itself.
         */
        private static function kavenegar( $s, $phone, $template, $vars ) {
                $values = array_values( $vars );
                $args   = [
                        'receptor' => $phone,
                        'template' => $template,
                        'token'    => str_replace( ' ', '‌', (string) ( $values[0] ?? '' ) ),
                ];
                if ( isset( $values[1] ) ) {
                        $args['token2'] = str_replace( ' ', '‌', (string) $values[1] );
                }
                if ( isset( $values[2] ) ) {
                        $args['token3'] = str_replace( ' ', '‌', (string) $values[2] );
                }
                $url  = 'https://api.kavenegar.com/v1/' . rawurlencode( $s[ self::cred_key( 'kavenegar', 'key' ) ] ) . '/verify/lookup.json';
                $resp = wp_remote_post( $url, [ 'timeout' => 15, 'body' => $args ] );
                return self::check( $resp, fn( $j ) => 200 === (int) ( $j['return']['status'] ?? 0 ), fn( $j ) => trim( (string) ( $j['return']['message'] ?? '' ) ) );
        }

        /** sms.ir v1 verify: numeric templateId + named parameters exactly as defined in the template. */
        private static function smsir( $s, $phone, $template, $vars ) {
                $params = [];
                foreach ( $vars as $name => $value ) {
                        $params[] = [ 'name' => (string) $name, 'value' => (string) $value ];
                }
                $resp = wp_remote_post( 'https://api.sms.ir/v1/send/verify', [
                        'timeout' => 15,
                        'headers' => [ 'X-API-KEY' => $s[ self::cred_key( 'smsir', 'key' ) ], 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                        'body'    => wp_json_encode( [ 'mobile' => $phone, 'templateId' => (int) $template, 'parameters' => $params ] ),
                ] );
                return self::check( $resp, fn( $j ) => 1 === (int) ( $j['status'] ?? 0 ), fn( $j ) => trim( (string) ( $j['message'] ?? '' ) ) );
        }

        /** IPPanel pattern send (api2 host + apikey header — the API the official SDKs implement). */
        private static function ippanel( $s, $phone, $template, $vars ) {
                $resp = wp_remote_post( 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send', [
                        'timeout' => 15,
                        'headers' => [ 'apikey' => $s[ self::cred_key( 'ippanel', 'key' ) ], 'Content-Type' => 'application/json' ],
                        'body'    => wp_json_encode( [
                                'code'      => $template,
                                'sender'    => $s[ self::cred_key( 'ippanel', 'sender' ) ],
                                'recipient' => $phone,
                                'variable'  => array_map( 'strval', $vars ),
                        ] ),
                ] );
                return self::check( $resp, fn( $j ) => 'OK' === ( $j['status'] ?? '' ), fn( $j ) => trim( (string) ( $j['errorMessage'] ?? '' ) ) );
        }

        /**
         * Melipayamak BaseServiceNumber: numeric bodyId of the approved template,
         * values joined with ';' in template order. Auth = panel username + API
         * key in the password field. One recipient per call, no sender needed.
         */
        private static function melipayamak( $s, $phone, $template, $vars ) {
                $body = [
                        'username' => $s[ self::cred_key( 'melipayamak', 'username' ) ],
                        'password' => $s[ self::cred_key( 'melipayamak', 'key' ) ], // API key, not the panel password.
                        'text'     => implode( ';', array_map( 'strval', array_values( $vars ) ) ),
                        'to'       => $phone,
                        'bodyId'   => (int) $template,
                ];
                $resp = wp_remote_post( 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber', [
                        'timeout' => 15,
                        'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
                        'body'    => wp_json_encode( $body ),
                ] );
                return self::check( $resp, fn( $j ) => 1 === (int) ( $j['RetStatus'] ?? 0 ), fn( $j ) => self::melipayamak_error( (int) ( $j['RetStatus'] ?? 0 ) ) );
        }

        /** RetStatus codes of Melipayamak, in Persian; anything else falls back to the raw code. */
        private static function melipayamak_error( $code ) {
                $map = [
                        -110   => 'این حساب ملی پیامک کلید API الزامی دارد؛ کلید را در فیلد «کلید API (رمز وب‌سرویس)» بگذارید.',
                        -4     => 'شناسهٔ الگو (BodyId) پیدا نشد یا هنوز تأیید نشده است.',
                        -5     => 'مقادیر ارسالی با متغیرهای الگو هم‌خوانی ندارد.',
                        -10    => 'در مقادیر الگوی ملی پیامک نوشتن لینک مجاز نیست.',
                        -111   => 'IP سرور در تنظیمات وب‌سرویس ملی پیامک مجاز نشده است.',
                        -112   => 'وب‌سرویس برای این حساب ملی پیامک غیرفعال است.',
                        0      => 'نام کاربری یا کلید API اشتباه است.',
                        2      => 'اعتبار پنل ملی پیامک کافی نیست.',
                        3      => 'به حد ارسال روزانهٔ پنل رسیده‌اید.',
                        5      => 'شمارهٔ فرستندهٔ پنل معتبر نیست.',
                        9      => 'ارسال با خط عمومی از وب‌سرویس ملی پیامک مجاز نیست؛ خط اختصاصی/خدماتی لازم است.',
                        11     => 'ارسال در ملی پیامک ناموفق بود.',
                        17     => 'متن پیامک خالی است.',
                        18     => 'شمارهٔ موبایل گیرنده معتبر نیست.',
                        35     => 'دادهٔ ارسالی به ملی پیامک نامعتبر است.',
                ];
                return $map[ $code ] ?? ( $code ? 'کد خطای ملی پیامک: ' . aim_fa_digits( (string) $code ) : '' );
        }

        /**
         * Shared response check: wp_remote_* error passes through; anything not
         * passing $ok becomes a Persian error with the provider's own detail
         * ($why) appended when it can be read.
         */
        private static function check( $resp, callable $ok, ?callable $why = null ) {
                if ( is_wp_error( $resp ) ) {
                        return $resp;
                }
                $json = json_decode( wp_remote_retrieve_body( $resp ), true );
                if ( wp_remote_retrieve_response_code( $resp ) >= 300 || ! is_array( $json ) || ! $ok( $json ) ) {
                        $detail = $why ? trim( (string) $why( $json ) ) : '';
                        return new WP_Error( 'aim_sms_failed', 'ارسال پیامک ناموفق بود' . ( '' !== $detail ? ': ' . $detail : ' (کد ' . wp_remote_retrieve_response_code( $resp ) . ' از درگاه پیامک).' ) );
                }
                return true;
        }

        /**
         * One-time migration from the pre-1.10 shared fields: the old single API
         * key/sender/pattern codes are copied into every legacy provider's own
         * keys, so the panel keeps working unchanged after the update. Melipayamak
         * gets nothing — its BodyId/username/API key live in a different namespace
         * and must be entered by hand. Runs once; the old keys stay in the option
         * untouched.
         */
        public static function maybe_migrate() {
                if ( get_option( 'aim_sms_v2_migrated' ) ) {
                        return;
                }
                $raw     = (array) get_option( AIM_Settings::OPTION, [] );
                $events  = array_keys( self::events() );
                $updates = [];
                foreach ( [ 'kavenegar', 'smsir', 'ippanel' ] as $driver ) {
                        if ( ! empty( $raw['sms_api_key'] ) ) {
                                $updates[ self::cred_key( $driver, 'key' ) ] = (string) $raw['sms_api_key'];
                        }
                        foreach ( $events as $event ) {
                                if ( ! empty( $raw[ 'sms_pattern_' . $event ] ) ) {
                                        $updates[ self::pattern_key( $driver, $event ) ] = (string) $raw[ 'sms_pattern_' . $event ];
                                }
                        }
                }
                if ( ! empty( $raw['sms_sender'] ) ) {
                        $updates[ self::cred_key( 'ippanel', 'sender' ) ] = (string) $raw['sms_sender'];
                }
                if ( $updates ) {
                        $merged = array_merge( AIM_Settings::all(), $updates );
                        unset( $merged['shop_title'] ); // lives in the blogname option only (see AIM_Settings::all()).
                        update_option( AIM_Settings::OPTION, $merged, false );
                }
                update_option( 'aim_sms_v2_migrated', 1, false );
        }
}

/* plugins_loaded, not admin_init: the first login OTP after an update runs on
 * the storefront via REST, before any admin screen is ever loaded. */
add_action( 'plugins_loaded', [ 'AIM_SMS', 'maybe_migrate' ] );
