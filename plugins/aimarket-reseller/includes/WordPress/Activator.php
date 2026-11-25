<?php
/**
 * Activation creates tables, the reseller capability, default tiers, and the docs page.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\WordPress;

use HooshMarket\Reseller\Domain\Crypto;
use HooshMarket\Reseller\Domain\Crm;
use HooshMarket\Reseller\Domain\Schema;
use HooshMarket\Reseller\Domain\Tables;
use HooshMarket\Reseller\Domain\Wallet;

final class Activator
{
    public static function activate(): void
    {
        global $wpdb;
        $db = new WpdbDatabase($wpdb);
        $prefix = $wpdb->prefix;
        Schema::install($db, $prefix);
        $tables = new Tables($prefix);
        $wallet = new Wallet($db, $tables->wallets(), $tables->ledger());
        (new Crm($db, $tables, $wallet))->seedDefaultTiers();

        $role = get_role('administrator');
        if ($role !== null && !$role->has_cap('manage_hm_resellers')) {
            $role->add_cap('manage_hm_resellers');
        }
        if (get_role('hm_reseller') === null) {
            add_role('hm_reseller', 'همکار فروش', ['read' => true]);
        }
        $shop = get_role('shop_manager');
        if ($shop !== null && !$shop->has_cap('manage_hm_resellers')) {
            $shop->add_cap('manage_hm_resellers');
        }
        if (!is_string(get_option('hmr_crypto_key')) || get_option('hmr_crypto_key') === '') {
            add_option('hmr_crypto_key', Crypto::generate()->export(), '', false);
        }
        if (!is_array(get_option('hmr_settings'))) {
            add_option('hmr_settings', [
                'accounts_table' => '',
                'available_status' => 'available',
                'sold_status' => 'sold',
                'columns' => [],
            ], '', false);
        }
        update_option('hmr_db_version', HMR_VERSION, false);

        Dashboard::registerEndpoint();
        flush_rewrite_rules();

        $existing = get_page_by_path('reseller-api-docs');
        if (!$existing instanceof \WP_Post) {
            wp_insert_post([
                'post_title' => 'مستندات API همکاری در فروش',
                'post_name' => 'reseller-api-docs',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '[hm_reseller_api_docs]',
            ]);
        }
        if (!wp_next_scheduled('hmr_dispatch_webhooks')) {
            wp_schedule_event(time() + 60, 'hmr_every_minute', 'hmr_dispatch_webhooks');
        }
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled('hmr_dispatch_webhooks');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'hmr_dispatch_webhooks');
        }
        flush_rewrite_rules();
    }
}
