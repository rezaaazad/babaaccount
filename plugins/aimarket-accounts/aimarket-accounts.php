<?php
/**
 * Plugin Name: AIM Panel — ماژول فروش اکانت
 * Description: ماژول فروش اکانت و اشتراک دیجیتال برای AIM Panel: انبار اکانت رمزنگاری‌شده، تحویل خودکار و دستی پس از پرداخت، اشتراک‌های فعال و یادآوری تمدید، تب «اکانت و تحویل» در محصول و بخش «اکانت‌های من» برای مشتری.
 * Version: 1.8.2
 * Requires at least: 6.5
 * Tested up to: 7.1
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce, aimarket-core
 * Text Domain: aimarket
 */

defined( 'ABSPATH' ) || exit;

define( 'AIMA_VERSION', '1.8.2' );
define( 'AIMA_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIMA_URL', plugin_dir_url( __FILE__ ) );

require_once AIMA_DIR . 'includes/class-crypto.php';
require_once AIMA_DIR . 'includes/class-secret-key.php';
require_once AIMA_DIR . 'includes/class-inventory.php';
require_once AIMA_DIR . 'includes/class-delivery.php';
require_once AIMA_DIR . 'includes/class-reminders.php';
require_once AIMA_DIR . 'includes/class-product-fields.php';
require_once AIMA_DIR . 'includes/class-accounts-install.php';
require_once AIMA_DIR . 'includes/class-accounts-api.php';
require_once AIMA_DIR . 'includes/class-accounts-panel.php';

register_activation_hook( __FILE__, function () {
	if ( class_exists( 'AIM_Install' ) ) {
		AIM_Accounts_Install::activate();
	}
} );
register_deactivation_hook( __FILE__, [ 'AIM_Accounts_Install', 'deactivate' ] );

// Runs once the AIM Panel core is loaded (its 'aim_loaded' action).
add_action( 'aim_loaded', function () {
	AIM_Accounts_Panel::init(); // First: adds this module's settings defaults.
	AIM_Accounts_Install::maybe_upgrade();
	AIM_Accounts_API::init();
	AIM_Secret_Key::init();
	AIM_Reminders::init();
	if ( class_exists( 'WooCommerce' ) ) {
		AIM_Delivery::init();
		AIM_Product_Fields::init();
		AIM_Inventory::init();
	}
} );

add_action( 'admin_notices', function () {
	if ( current_user_can( 'manage_options' ) && ! defined( 'AIM_SECRET_KEY' ) ) {
		echo '<div class="notice notice-warning"><p>کلید اختصاصی رمزنگاری اکانت‌ها تنظیم نشده است؛ قبل از تغییر کلیدهای امنیتی وردپرس نسخه پشتیبان بگیرید.</p></div>';
	}
	if ( ! did_action( 'aim_loaded' ) && current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p>ماژول فروش اکانت برای کار کردن به افزونهٔ «AIM Panel — پنل مدیریت فروشگاه» نیاز دارد.</p></div>';
	}
} );
