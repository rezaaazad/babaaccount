<?php
/**
 * Plugin Name: AIM Panel — پنل مدیریت سایت
 * Description: پنل مدیریت تمام‌صفحه برای هر سایت وردپرسی، با راه‌اندازی خودکار بر اساس نوع کسب‌وکار (فروشگاهی، خدماتی، مهاجرت، آموزشی و…): همهٔ بخش‌های وردپرس داخل پنل، ورود با کد پیامکی، کدهای سفارشی و تنظیمات قالب. با ووکامرس، بخش‌های فروشگاه (محصولات، سفارش‌ها، مدیریت گروهی قیمت، اعلان موجودی) هم فعال می‌شوند.
 * Version: 1.12.1
 * Requires at least: 6.5
 * Tested up to: 7.1
 * Requires PHP: 8.0
 * Text Domain: aimarket
 */

defined( 'ABSPATH' ) || exit;

define( 'AIM_VERSION', '1.12.1' );
define( 'AIM_FILE', __FILE__ );
define( 'AIM_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIM_URL', plugin_dir_url( __FILE__ ) );

require_once AIM_DIR . 'includes/helpers.php';
require_once AIM_DIR . 'includes/class-install.php';
require_once AIM_DIR . 'includes/class-settings.php';
require_once AIM_DIR . 'includes/class-sms.php';
require_once AIM_DIR . 'includes/class-otp.php';
require_once AIM_DIR . 'includes/class-activity.php';
require_once AIM_DIR . 'includes/class-panel-menu.php';
require_once AIM_DIR . 'includes/class-router.php';
require_once AIM_DIR . 'includes/class-admin-api.php';
require_once AIM_DIR . 'includes/class-wp-admin.php';
require_once AIM_DIR . 'includes/class-wp-embed.php';
require_once AIM_DIR . 'includes/class-bulk-prices.php';
require_once AIM_DIR . 'includes/class-stock-alerts.php';
require_once AIM_DIR . 'includes/class-site-tweaks.php';
require_once AIM_DIR . 'includes/class-order-log.php';
require_once AIM_DIR . 'includes/class-theme-options.php';
require_once AIM_DIR . 'includes/class-product-display.php';
require_once AIM_DIR . 'includes/class-setup.php';
require_once AIM_DIR . 'includes/class-contract.php';

register_activation_hook( __FILE__, [ 'AIM_Install', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'AIM_Install', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
        AIM_Install::maybe_upgrade();
        AIM_OTP::init();
        AIM_Router::init();
        AIM_Admin_API::init();
        AIM_WP_Admin::init();
        AIM_WP_Embed::init();
        AIM_Site_Tweaks::init();
        AIM_Theme_Options::init();
        AIM_Setup::init();

        // WooCommerce is optional: store sections appear when it is active (see AIM_Panel_Menu 'wc').
        if ( class_exists( 'WooCommerce' ) ) {
                AIM_Product_Display::init();
                AIM_Bulk_Prices::init();
                AIM_Stock_Alerts::init();
                AIM_Contract::init();
                AIM_Order_Log::init();
        }

        // Business modules (e.g. aimarket-accounts) hook in here.
        do_action( 'aim_loaded' );
} );
add_action( 'init', static function () {
	load_plugin_textdomain( 'aimarket', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );
add_action( 'admin_notices', static function () {
	if ( current_user_can( 'manage_options' ) && 'log' === AIM_Settings::get( 'sms_driver' ) && ! in_array( wp_get_environment_type(), [ 'local', 'development' ], true ) ) {
		echo '<div class="notice notice-error"><p>درگاه پیامک تنظیم نشده است؛ ارسال پیامک در محیط اصلی غیرفعال است.</p></div>';
	}
} );
