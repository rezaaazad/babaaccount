<?php
/**
 * Plugin Name: هوش‌مارکت — همکاری در فروش
 * Description: حساب همکار، کیف پول، قیمت سطح‌بندی‌شده و API خرید اکانت روی ووکامرس و aimarket-accounts.
 * Version: 1.0.1
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce, aimarket-core, aimarket-accounts
 * Author: HooshMarket
 * Text Domain: aimarket-reseller
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('HMR_VERSION', '1.0.1');
define('HMR_FILE', __FILE__);
define('HMR_DIR', __DIR__);

require __DIR__ . '/includes/autoload.php';

register_activation_hook(HMR_FILE, [\HooshMarket\Reseller\WordPress\Activator::class, 'activate']);
register_deactivation_hook(HMR_FILE, [\HooshMarket\Reseller\WordPress\Activator::class, 'deactivate']);

\HooshMarket\Reseller\WordPress\Plugin::boot();
