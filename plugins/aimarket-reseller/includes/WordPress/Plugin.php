<?php
/**
 * Boots the plugin after WooCommerce has loaded.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\WordPress;

use HooshMarket\Reseller\Domain\Auth;
use HooshMarket\Reseller\Domain\Crm;
use HooshMarket\Reseller\Domain\Crypto;
use HooshMarket\Reseller\Domain\OrderService;
use HooshMarket\Reseller\Domain\Pricing;
use HooshMarket\Reseller\Domain\RequestLog;
use HooshMarket\Reseller\Domain\Tables;
use HooshMarket\Reseller\Domain\Wallet;
use HooshMarket\Reseller\Domain\WebhookQueue;

final class Plugin
{
    private static ?Services $services = null;

    public static function boot(): void
    {
        add_action('plugins_loaded', [self::class, 'init'], 20);
        add_filter('cron_schedules', [self::class, 'cron']);
        add_action('hmr_dispatch_webhooks', [self::class, 'dispatchWebhooks']);
    }

    /**
     * @param array<string, array<string, mixed>> $schedules
     * @return array<string, array<string, mixed>>
     */
    public static function cron(array $schedules): array
    {
        $schedules['hmr_every_minute'] = [
            'interval' => 60,
            'display' => 'هر دقیقه — وب‌هوک همکاری',
        ];
        return $schedules;
    }

    public static function init(): void
    {
        load_plugin_textdomain('aimarket-reseller', false, dirname(plugin_basename(HMR_FILE)) . '/languages');
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>افزونه همکاری در فروش هوش‌مارکت به ووکامرس نیاز دارد.</p></div>';
            });
            return;
        }
        $services = self::services();
        (new RestController($services))->register();
        (new AdminPage($services))->register();
        (new Dashboard($services))->register();
        (new WooHooks($services))->register();
    }

    public static function dispatchWebhooks(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }
        self::services()->webhooks->dispatchDue();
    }

    public static function services(): Services
    {
        if (self::$services instanceof Services) {
            return self::$services;
        }
        global $wpdb;
        $db = new WpdbDatabase($wpdb);
        $tables = new Tables($wpdb->prefix);
        $crypto = self::crypto();
        $currency = function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : 'IRT';
        if ($currency === '') {
            $currency = 'IRT';
        }
        $decimals = function_exists('wc_get_price_decimals') ? (int) wc_get_price_decimals() : 0;
        $wallet = new Wallet($db, $tables->wallets(), $tables->ledger());
        $catalog = new WooCatalog($decimals, $currency);
        $bridge = new AimarketFulfillment($db);
        $pricing = new Pricing($db, $tables);
        $sender = static function (string $url, string $body, array $headers): array {
            $response = wp_remote_post($url, [
                'timeout' => 10,
                'redirection' => 0,
                'headers' => $headers,
                'body' => $body,
            ]);
            if (is_wp_error($response)) {
                return ['code' => 0, 'error' => $response->get_error_message()];
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            $error = $code >= 200 && $code < 300 ? '' : 'HTTP ' . $code;
            return ['code' => $code, 'error' => $error];
        };
        self::$services = new Services(
            $db,
            $tables,
            $crypto,
            $wallet,
            $pricing,
            $catalog,
            $bridge,
            new OrderService($db, $tables, $wallet, $pricing, $catalog, $bridge, $crypto),
            new Auth($db, $tables),
            new Crm($db, $tables, $wallet),
            new WebhookQueue($db, $tables, $crypto, $sender),
            new RequestLog($db, $tables),
            $decimals,
            $currency
        );
        return self::$services;
    }

    private static function crypto(): Crypto
    {
        $stored = get_option('hmr_crypto_key');
        if (!is_string($stored) || $stored === '') {
            $crypto = Crypto::generate();
            add_option('hmr_crypto_key', $crypto->export(), '', false);
            return $crypto;
        }
        return Crypto::fromExport($stored);
    }
}
