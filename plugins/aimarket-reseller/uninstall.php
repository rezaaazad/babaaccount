<?php
/**
 * Drops reseller tables only when the admin opted in. The crypto key and
 * ledger are kept by default so a reinstall does not wipe balances.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (get_option('hmr_remove_data_on_uninstall') !== 'yes') {
    return;
}

global $wpdb;
$tables = [
    'hmr_webhooks',
    'hmr_api_logs',
    'hmr_deliveries',
    'hmr_orders',
    'hmr_idempotency',
    'hmr_rate_buckets',
    'hmr_api_keys',
    'hmr_ledger',
    'hmr_wallets',
    'hmr_tier_prices',
    'hmr_tiers',
    'hmr_resellers',
];
foreach ($tables as $table) {
    $name = $wpdb->prefix . $table;
    if (preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        $wpdb->query("DROP TABLE IF EXISTS {$name}");
    }
}
delete_option('hmr_crypto_key');
delete_option('hmr_settings');
delete_option('hmr_db_version');
delete_option('hmr_remove_data_on_uninstall');
