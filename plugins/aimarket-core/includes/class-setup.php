<?php
defined( 'ABSPATH' ) || exit;

/**
 * Setup wizard (panel → «راه‌اندازی سریع», opened right after activation).
 *
 * The visitor picks a business type, fills in the business details and
 * reviews the features suggested for that type; the wizard then runs one
 * task per request (so installing WooCommerce cannot time out the page):
 *
 *   POST /setup/start { type, info: {…}, features: {…} }  → { tasks: [ { id, label } ] }
 *   POST /setup/task  { id }                              → { ok, message }
 *
 * Profiles (self::types()) decide the defaults: store types get WooCommerce
 * installed and configured for Iran; the others get the store sections of
 * the panel switched off (they can be switched on later in «منوی پنل»).
 */
class AIM_Setup {

	const OPTION   = 'aim_setup';         // [ type, info, features, done ].
	const REDIRECT = 'aim_setup_redirect'; // Transient set on activation.
	const WOO      = 'woocommerce/woocommerce.php';
	const ACCOUNTS = 'aimarket-accounts/aimarket-accounts.php';

	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'maybe_redirect' ] );
		add_action( 'rest_api_init', function () {
			$perm = fn() => current_user_can( 'manage_options' );
			register_rest_route( AIM_Admin_API::NS, '/setup/start', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'rest_start' ], 'permission_callback' => $perm ] );
			register_rest_route( AIM_Admin_API::NS, '/setup/task', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'rest_task' ], 'permission_callback' => $perm ] );
		} );
	}

	/** Business types and what each one switches on. */
	public static function types() {
		return [
			'store'       => [ 'label' => 'فروشگاه اینترنتی', 'text' => 'فروش کالای فیزیکی با ارسال پستی؛ مثل موبایل و لوازم جانبی، پوشاک یا لوازم خانگی.', 'icon' => 'shopping-bag-3-line', 'shop' => true ],
			'digital'     => [ 'label' => 'محصول دیجیتال و اکانت', 'text' => 'فروش اکانت، اشتراک، لایسنس یا فایل با تحویل خودکار بعد از پرداخت.', 'icon' => 'key-2-line', 'shop' => true, 'accounts' => true ],
			'services'    => [ 'label' => 'خدمات و مشاوره', 'text' => 'معرفی خدمات، دریافت درخواست و ارتباط با مشتری؛ مثل طراحی، تعمیرات یا مشاوره.', 'icon' => 'customer-service-2-line' ],
			'immigration' => [ 'label' => 'مهاجرت و ویزا', 'text' => 'معرفی خدمات مهاجرتی، مقاله‌های راهنما و ارتباط با متقاضیان.', 'icon' => 'compass-3-line' ],
			'education'   => [ 'label' => 'آموزش و دوره', 'text' => 'آموزشگاه، مدرس یا آکادمی آنلاین؛ با امکان فروش دوره در صورت نیاز.', 'icon' => 'book-open-line' ],
			'company'     => [ 'label' => 'شرکتی و معرفی', 'text' => 'سایت معرفی شرکت یا برند با برگه‌ها، اخبار و راه‌های تماس.', 'icon' => 'store-2-line' ],
			'other'       => [ 'label' => 'سایر', 'text' => 'هر کار دیگری؛ بعداً هر بخشی را لازم داشتید روشن کنید.', 'icon' => 'apps-2-line' ],
		];
	}

	/** Feature switches shown in step 3, with the default for a business type. */
	public static function features( $type ) {
		$t = self::types()[ $type ] ?? self::types()['other'];
		return [
			'shop'     => [ 'label' => 'فروش آنلاین (ووکامرس)', 'text' => 'محصولات، سبد خرید، پرداخت و سفارش‌ها. اگر نصب نباشد خودکار نصب می‌شود.', 'on' => ! empty( $t['shop'] ) ],
			'accounts' => [ 'label' => 'تحویل خودکار اکانت', 'text' => 'انبار اکانت و تحویل بلافاصله بعد از پرداخت (ماژول فروش اکانت).', 'on' => ! empty( $t['accounts'] ), 'needs' => 'accounts' ],
			'otp'      => [ 'label' => 'ورود با کد پیامکی', 'text' => 'کاربران با شمارهٔ موبایل و یک کد وارد می‌شوند؛ ورود با رمز هم فعال می‌ماند. (همیشه روشن است)', 'on' => true, 'locked' => true ],
			'pages'    => [ 'label' => 'ساخت برگه‌های پایه', 'text' => '«درباره ما»، «تماس با ما» و «قوانین و شرایط» با متن نمونه.', 'on' => true ],
			'speed'    => [ 'label' => 'بهینه‌سازی سرعت', 'text' => 'حذف اسکریپت‌های اضافهٔ وردپرس (ایموجی، Embed).', 'on' => true ],
		];
	}

	public static function state() {
		return wp_parse_args( (array) get_option( self::OPTION, [] ), [ 'type' => '', 'info' => [], 'features' => [], 'done' => 0 ] );
	}

	public static function done() {
		return (bool) self::state()['done'];
	}

	/** Right after activation, open the wizard once (not for bulk activation or when it was already run). */
	public static function maybe_redirect() {
		if ( ! get_transient( self::REDIRECT ) || wp_doing_ajax() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		delete_transient( self::REDIRECT );
		if ( ! self::done() && current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( AIM_WP_Admin::url( 'setup' ) );
			exit;
		}
	}

	// ---- REST ----

	public static function rest_start( WP_REST_Request $req ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php'; // is_plugin_active() is not loaded in REST requests.
		$type = sanitize_key( $req->get_param( 'type' ) );
		if ( ! isset( self::types()[ $type ] ) ) {
			return self::fail( 'نوع کسب‌وکار را انتخاب کنید.' );
		}
		$in   = (array) $req->get_param( 'info' );
		$info = [
			'name'    => sanitize_text_field( $in['name'] ?? '' ),
			'tagline' => sanitize_text_field( $in['tagline'] ?? '' ),
			'phone'   => sanitize_text_field( aim_en_digits( (string) ( $in['phone'] ?? '' ) ) ),
			'email'   => sanitize_email( $in['email'] ?? '' ),
			'hours'   => sanitize_text_field( $in['hours'] ?? '' ),
			'mode'    => in_array( $in['mode'] ?? '', [ 'light', 'dark', 'auto' ], true ) ? $in['mode'] : 'light',
		];
		if ( '' === $info['name'] ) {
			return self::fail( 'نام کسب‌وکار را وارد کنید.' );
		}
		if ( '' !== ( $in['email'] ?? '' ) && ! is_email( $info['email'] ) ) {
			return self::fail( 'ایمیل معتبر نیست.' );
		}
		$features = [];
		foreach ( self::features( $type ) as $key => $f ) {
			$features[ $key ] = ! empty( ( (array) $req->get_param( 'features' ) )[ $key ] );
		}
		if ( $features['accounts'] && ! self::plugin_installed( self::ACCOUNTS ) ) {
			$features['accounts'] = false; // Module not on this site.
		}
		$features['accounts'] = $features['accounts'] && $features['shop'];
		// Keep an earlier run's «done» so an abandoned re-run does not mark the site as never set up.
		update_option( self::OPTION, [ 'type' => $type, 'info' => $info, 'features' => $features, 'done' => self::state()['done'] ], false );

		$tasks = [ [ 'id' => 'basics', 'label' => 'ثبت مشخصات سایت و زمان ایران' ] ];
		if ( $features['shop'] ) {
			if ( ! self::plugin_installed( self::WOO ) ) {
				$tasks[] = [ 'id' => 'woo-install', 'label' => 'دانلود و نصب ووکامرس' ];
			}
			if ( ! is_plugin_active( self::WOO ) ) {
				$tasks[] = [ 'id' => 'woo-activate', 'label' => 'فعال‌سازی ووکامرس' ];
			}
			$tasks[] = [ 'id' => 'woo-config', 'label' => 'تنظیم فروشگاه برای ایران (تومان، کشور، پرداخت)' ];
		}
		if ( $features['accounts'] && ! is_plugin_active( self::ACCOUNTS ) ) {
			$tasks[] = [ 'id' => 'accounts', 'label' => 'فعال‌سازی ماژول فروش اکانت' ];
		}
		if ( $features['pages'] ) {
			$tasks[] = [ 'id' => 'pages', 'label' => 'ساخت برگه‌های درباره ما، تماس و قوانین' ];
		}
		$tasks[] = [ 'id' => 'panel', 'label' => 'چیدن منوی پنل برای ' . self::types()[ $type ]['label'] ];
		$tasks[] = [ 'id' => 'finish', 'label' => 'نهایی‌سازی' ];
		return rest_ensure_response( [ 'ok' => true, 'tasks' => $tasks ] );
	}

	public static function rest_task( WP_REST_Request $req ) {
		$id     = sanitize_key( $req->get_param( 'id' ) );
		$method = 'task_' . str_replace( '-', '_', $id );
		if ( ! method_exists( __CLASS__, $method ) ) {
			return self::fail( 'مرحلهٔ نامعتبر.' );
		}
		$st = self::state();
		// Tasks read what /setup/start saved; without it «basics» would blank the site name.
		if ( ! isset( self::types()[ $st['type'] ] ) || empty( $st['info']['name'] ) ) {
			return self::fail( 'اطلاعات راه‌اندازی پیدا نشد؛ راه‌اندازی را از ابتدا شروع کنید.' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php'; // activate_plugin().
		$result = self::$method( $st );
		return is_wp_error( $result ) ? self::fail( $result->get_error_message() ) : rest_ensure_response( [ 'ok' => true, 'message' => (string) $result ] );
	}

	/**
	 * Apply a business type in one go, without the wizard (used by a theme
	 * that installs the plugins itself, e.g. HooshMarket → 'digital').
	 * Plugins must already be installed and active; pages are left to the theme.
	 *
	 * @return string[] Messages of the tasks that ran.
	 */
	public static function apply_preset( $type, array $info = [] ) {
		$features = array_map( fn( $f ) => (bool) $f['on'], self::features( $type ) );
		$features['shop']  = $features['shop'] && class_exists( 'WooCommerce' );
		$features['pages'] = false;
		$st = [
			'type'     => $type,
			// Defaults are the current values, so re-running the theme setup keeps the contact details and colour mode.
			'info'     => $info + [ 'name' => get_bloginfo( 'name' ), 'tagline' => get_bloginfo( 'description' ), 'phone' => AIM_Settings::get( 'support_phone' ), 'email' => AIM_Settings::get( 'support_email' ) ?: get_option( 'admin_email' ), 'hours' => AIM_Settings::get( 'response_time' ), 'mode' => AIM_Settings::get( 'theme_mode' ) ],
			'features' => $features,
			'done'     => 0,
		];
		update_option( self::OPTION, $st, false );
		delete_transient( self::REDIRECT );
		$messages = [ self::task_basics( $st ) ];
		if ( $features['shop'] ) {
			$messages[] = self::task_woo_config( $st );
		}
		$messages[] = self::task_panel( $st );
		$messages[] = self::task_finish( $st );
		return $messages;
	}

	// ---- Tasks (each returns a short message or WP_Error) ----

	private static function task_basics( array $st ) {
		$i = $st['info'];
		update_option( 'blogname', $i['name'] );
		if ( '' !== $i['tagline'] ) {
			update_option( 'blogdescription', $i['tagline'] );
		}
		update_option( 'timezone_string', 'Asia/Tehran' );
		update_option( 'start_of_week', 6 ); // Saturday.
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		flush_rewrite_rules( false );
		$speed = ! empty( $st['features']['speed'] );
		AIM_Settings::update( [
			'shop_title'         => $i['name'],
			'support_phone'      => $i['phone'],
			'support_email'      => $i['email'] ?: get_option( 'admin_email' ),
			'response_time'      => $i['hours'] ?: AIM_Settings::get( 'response_time' ),
			'theme_mode'         => $i['mode'],
			'opt_disable_emojis' => (int) $speed,
			'opt_disable_embeds' => (int) $speed,
		] );
		return 'نام سایت، تماس، زمان ایران و آدرس‌های خوانا تنظیم شد.';
	}

	private static function task_woo_install() {
		if ( self::plugin_installed( self::WOO ) ) {
			return 'ووکامرس از قبل نصب است.';
		}
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error( 'cap', 'حساب شما اجازهٔ نصب افزونه ندارد.' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$api = plugins_api( 'plugin_information', [ 'slug' => 'woocommerce', 'fields' => [ 'sections' => false ] ] );
		if ( is_wp_error( $api ) ) {
			return new WP_Error( 'api', 'اتصال به مخزن وردپرس برقرار نشد؛ ووکامرس را از «همهٔ بخش‌های وردپرس ← افزونه‌ها» نصب کنید.' );
		}
		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->install( $api->download_link );
		if ( true !== $result ) {
			return new WP_Error( 'install', 'نصب ووکامرس انجام نشد (احتمالاً هاست اجازهٔ نوشتن در پوشهٔ افزونه‌ها را نمی‌دهد). از «همهٔ بخش‌های وردپرس ← افزونه‌ها» دستی نصب کنید.' );
		}
		return 'ووکامرس نصب شد.';
	}

	private static function task_woo_activate() {
		$result = activate_plugin( self::WOO );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'activate', 'فعال‌سازی ووکامرس انجام نشد: ' . $result->get_error_message() );
		}
		// The panel does the setup; skip WooCommerce's own onboarding screens.
		delete_transient( '_wc_activation_redirect' );
		update_option( 'woocommerce_onboarding_profile', [ 'skipped' => true ] );
		return 'ووکامرس فعال شد.';
	}

	private static function task_woo_config( array $st ) {
		$digital = 'digital' === $st['type'];
		foreach ( [
			'woocommerce_currency'                 => 'IRT',
			'woocommerce_default_country'          => 'IR',
			'woocommerce_currency_pos'             => 'right_space',
			'woocommerce_price_num_decimals'       => 0,
			'woocommerce_price_thousand_sep'       => ',',
			'woocommerce_allowed_countries'        => 'specific',
			'woocommerce_specific_allowed_countries' => [ 'IR' ],
			'woocommerce_calc_taxes'               => 'no',
			'woocommerce_enable_guest_checkout'    => 'no',
			'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
			'woocommerce_ship_to_countries'        => $digital ? 'disabled' : '',
			'woocommerce_calc_shipping'            => $digital ? 'no' : 'yes',
			'woocommerce_coming_soon'              => 'no',
		] as $key => $value ) {
			if ( '' !== $value ) {
				update_option( $key, $value );
			}
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'woo', 'تنظیمات فروشگاه ذخیره شد، ولی ووکامرس فعال نیست؛ پس از فعال کردن ووکامرس اعمال می‌شود.' );
		}
		if ( class_exists( 'WC_Install' ) ) {
			WC_Install::create_pages();
		}
		return $digital ? 'فروشگاه برای کالای دیجیتال (بدون ارسال) و تومان تنظیم شد.' : 'فروشگاه برای ایران و تومان تنظیم شد؛ روش‌های ارسال را در تنظیمات ووکامرس اضافه کنید.';
	}

	private static function task_accounts() {
		$result = activate_plugin( self::ACCOUNTS );
		return is_wp_error( $result ) ? new WP_Error( 'activate', 'فعال‌سازی ماژول اکانت انجام نشد: ' . $result->get_error_message() ) : 'ماژول فروش اکانت فعال شد.';
	}

	private static function task_pages( array $st ) {
		$i     = $st['info'];
		$made  = 0;
		$pages = [
			'about'   => [ 'درباره ما', '<h2>' . esc_html( $i['name'] ) . '</h2><p>اینجا دربارهٔ کسب‌وکارتان، تجربه و ارزش‌هایتان بنویسید.</p>' ],
			'contact' => [ 'تماس با ما', '<p>برای ارتباط با ما:</p><ul>' . ( $i['phone'] ? '<li>تلفن: ' . esc_html( aim_fa_digits( $i['phone'] ) ) . '</li>' : '' ) . ( $i['email'] ? '<li>ایمیل: ' . esc_html( $i['email'] ) . '</li>' : '' ) . ( $i['hours'] ? '<li>ساعات پاسخگویی: ' . esc_html( $i['hours'] ) . '</li>' : '' ) . '</ul>' ],
			'terms'   => [ 'قوانین و شرایط', '<p>قوانین استفاده از خدمات ' . esc_html( $i['name'] ) . ' را اینجا بنویسید.</p>' ],
		];
		foreach ( $pages as $slug => [ $title, $content ] ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'trash' !== $page->post_status ) {
				continue; // Already there (the theme may have built it with Elementor).
			}
			$id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ], true );
			if ( ! is_wp_error( $id ) ) {
				$made++;
			}
		}
		return $made ? sprintf( '%s برگه ساخته شد.', aim_fa_digits( $made ) ) : 'برگه‌ها از قبل وجود داشتند.';
	}

	/**
	 * Store types: the store sections on. Others: the store sections off (switch them on later in
	 * «منوی پنل»). Only the store items change; the admin's other «منوی پنل» choices are kept.
	 */
	private static function task_panel( array $st ) {
		$store = [];
		foreach ( AIM_Panel_Menu::admin( true, true ) as $group ) {
			foreach ( $group['items'] as $item ) {
				if ( ! empty( $item['wc'] ) ) {
					$store[] = $item['key'];
				}
			}
		}
		$hidden = array_values( array_diff( (array) get_option( AIM_Panel_Menu::HIDDEN_OPTION, [] ), $store ) );
		$shop   = ! empty( $st['features']['shop'] );
		if ( ! $shop ) {
			$hidden = array_values( array_unique( array_merge( $hidden, $store ) ) );
		}
		update_option( AIM_Panel_Menu::HIDDEN_OPTION, $hidden, true );
		return $shop ? 'بخش‌های فروشگاهی پنل روشن است.' : 'بخش‌های فروشگاهی از منو خاموش شد.';
	}

	private static function task_finish( array $st ) {
		$st['done'] = time();
		update_option( self::OPTION, $st, false );
		AIM_Activity::log( 'setup_done', 'راه‌اندازی سریع انجام شد: ' . ( self::types()[ $st['type'] ]['label'] ?? $st['type'] ), 'settings' );
		return 'همه‌چیز آماده است.';
	}

	// ---- Helpers ----

	public static function plugin_installed( $file ) {
		return file_exists( WP_PLUGIN_DIR . '/' . $file );
	}

	private static function fail( $message ) {
		return new WP_REST_Response( [ 'ok' => false, 'message' => $message ], 400 );
	}
}
