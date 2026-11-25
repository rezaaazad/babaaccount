<?php
/**
 * Table definitions for MySQL/MariaDB (WordPress) and SQLite (tests).
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class Schema
{
    public static function install(Database $db, string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
            throw new \InvalidArgumentException('Unsafe table prefix.');
        }
        foreach (self::statements($db->driver(), $prefix) as $sql) {
            $db->exec($sql);
        }
    }

    /**
     * @return list<string>
     */
    public static function statements(string $driver, string $prefix): array
    {
        $tables = new Tables($prefix);
        if ($driver === 'sqlite') {
            return self::sqlite($tables);
        }
        return self::mysql($tables);
    }

    /**
     * @return list<string>
     */
    private static function mysql(Tables $t): array
    {
        $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS {$t->resellers()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                tier_id BIGINT UNSIGNED NULL,
                display_name VARCHAR(190) NOT NULL,
                phone VARCHAR(40) NOT NULL DEFAULT '',
                company VARCHAR(190) NOT NULL DEFAULT '',
                note TEXT NULL,
                webhook_url TEXT NULL,
                webhook_secret_enc TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY user_id (user_id),
                KEY status (status)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->tiers()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug VARCHAR(40) NOT NULL,
                name VARCHAR(80) NOT NULL,
                discount_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->tierPrices()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tier_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                price_minor BIGINT NULL,
                discount_percent DECIMAL(6,2) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY tier_product (tier_id, product_id)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->wallets()} (
                reseller_id BIGINT UNSIGNED NOT NULL,
                balance_minor BIGINT NOT NULL DEFAULT 0,
                currency VARCHAR(8) NOT NULL,
                version INT NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (reseller_id)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->ledger()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                reseller_id BIGINT UNSIGNED NOT NULL,
                direction VARCHAR(10) NOT NULL,
                entry_type VARCHAR(32) NOT NULL,
                amount_minor BIGINT NOT NULL,
                balance_after BIGINT NOT NULL,
                reference_type VARCHAR(40) NOT NULL DEFAULT '',
                reference_id VARCHAR(64) NOT NULL DEFAULT '',
                note TEXT NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY reseller_created (reseller_id, created_at)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->apiKeys()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                reseller_id BIGINT UNSIGNED NOT NULL,
                label VARCHAR(80) NOT NULL DEFAULT '',
                key_prefix VARCHAR(24) NOT NULL,
                key_hash CHAR(64) NOT NULL,
                ip_allowlist TEXT NULL,
                rate_limit INT NOT NULL DEFAULT 60,
                last_used_at DATETIME NULL,
                revoked_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY key_prefix (key_prefix),
                KEY reseller_id (reseller_id)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->rateBuckets()} (
                key_id BIGINT UNSIGNED NOT NULL,
                window_start INT NOT NULL,
                hits INT NOT NULL DEFAULT 0,
                PRIMARY KEY (key_id, window_start)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->idempotency()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                reseller_id BIGINT UNSIGNED NOT NULL,
                idem_key VARCHAR(128) NOT NULL,
                request_hash CHAR(64) NOT NULL,
                status VARCHAR(20) NOT NULL,
                response_code INT NOT NULL DEFAULT 0,
                resource_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY reseller_key (reseller_id, idem_key)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->orders()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                reseller_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                quantity INT NOT NULL,
                unit_price_minor BIGINT NOT NULL,
                unit_cost_minor BIGINT NULL,
                total_minor BIGINT NOT NULL,
                currency VARCHAR(8) NOT NULL,
                status VARCHAR(20) NOT NULL,
                client_reference VARCHAR(120) NOT NULL DEFAULT '',
                wc_order_id BIGINT UNSIGNED NULL,
                error_code VARCHAR(60) NOT NULL DEFAULT '',
                error_message TEXT NULL,
                ledger_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY reseller_created (reseller_id, created_at)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->deliveries()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                account_ref VARCHAR(64) NOT NULL DEFAULT '',
                payload_enc MEDIUMTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY order_id (order_id)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->apiLogs()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                request_id VARCHAR(32) NOT NULL,
                reseller_id BIGINT UNSIGNED NULL,
                key_id BIGINT UNSIGNED NULL,
                method VARCHAR(10) NOT NULL,
                route VARCHAR(190) NOT NULL,
                status_code INT NOT NULL,
                ip VARCHAR(45) NOT NULL DEFAULT '',
                duration_ms INT NOT NULL DEFAULT 0,
                error_code VARCHAR(60) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY created_at (created_at),
                KEY reseller_created (reseller_id, created_at)
            ) {$engine}",
            "CREATE TABLE IF NOT EXISTS {$t->webhooks()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                reseller_id BIGINT UNSIGNED NOT NULL,
                event_name VARCHAR(40) NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                target_url TEXT NOT NULL,
                payload_enc MEDIUMTEXT NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                max_attempts INT NOT NULL DEFAULT 5,
                next_attempt_at DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL,
                last_response_code INT NULL,
                last_error TEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY status_next (status, next_attempt_at)
            ) {$engine}",
        ];
    }

    /**
     * @return list<string>
     */
    private static function sqlite(Tables $t): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS {$t->resellers()} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT 'pending',
                tier_id INTEGER NULL,
                display_name TEXT NOT NULL,
                phone TEXT NOT NULL DEFAULT '',
                company TEXT NOT NULL DEFAULT '',
                note TEXT NULL,
                webhook_url TEXT NULL,
                webhook_secret_enc TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$t->tiers()} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                discount_percent REAL NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_default INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$t->tierPrices()} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tier_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                price_minor INTEGER NULL,
                discount_percent REAL NULL,
                UNIQUE (tier_id, product_id)
            )",
            "CREATE TABLE IF NOT EXISTS {$t->wallets()} (
                reseller_id INTEGER PRIMARY KEY,
                balance_minor INTEGER NOT NULL DEFAULT 0,
                currency TEXT NOT NULL,
                version INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$t->ledger()} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reseller_id INTEGER NOT NULL,
                direction TEXT NOT NULL,
                entry_type TEXT NOT NULL,
                amount_minor INTEGER NOT NULL,
                balance_after INTEGER NOT NULL,
                reference_type TEXT NOT NULL DEFAULT '',
                reference_id TEXT NOT NULL DEFAULT '',
                note TEXT NULL,
                actor_user_id INTEGER NULL,
                created_at TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_{$t->prefix()}hmr_ledger_reseller ON {$t->ledger()} (reseller_id, created_at)",
            "CREATE TABLE IF NOT EXISTS {$t->apiKeys()} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reseller_id INTEGER NOT NULL,
                label TEXT NOT NULL DEFAULT '',
                key_prefix TEXT NOT NULL,
                key_hash TEXT NOT NULL,
                ip_allowlist TEXT NULL,
                rate_limit INTEGER NOT NULL DEFAULT 60,
                last_used_at TEXT NULL,
                revoked_at TEXT NULL,
                created_at TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_{$t->prefix()}hmr_keys_prefix ON {$t->apiKeys()} (key_prefix)",
            "CREATE TABLE IF NOT EXISTS {$t->rateBuckets()} (
                key_id INTEGER NOT NULL,
                window_start INTEGER NOT NULL,
                hits INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (key_id, window_start)
            )",
            "CREATE TABLE IF NOT EXISTS {$t->idempotency()} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reseller_id INTEGER NOT NULL,
                idem_key TEXT NOT NULL,
                request_hash TEXT NOT NULL,
                status TEXT NOT NULL,
                response_code INTEGER NOT NULL DEFAULT 0,
                resource_id INTEGER NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (reseller_id, idem_key)
            )",
            "CREATE TABLE IF NOT EXISTS {$t->orders()} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reseller_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                unit_price_minor INTEGER NOT NULL,
                unit_cost_minor INTEGER NULL,
                total_minor INTEGER NOT NULL,
                currency TEXT NOT NULL,
                status TEXT NOT NULL,
                client_reference TEXT NOT NULL DEFAULT '',
                wc_order_id INTEGER NULL,
                error_code TEXT NOT NULL DEFAULT '',
                error_message TEXT NULL,
                ledger_id INTEGER NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$t->deliveries()} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                account_ref TEXT NOT NULL DEFAULT '',
                payload_enc TEXT NOT NULL,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$t->apiLogs()} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_id TEXT NOT NULL,
                reseller_id INTEGER NULL,
                key_id INTEGER NULL,
                method TEXT NOT NULL,
                route TEXT NOT NULL,
                status_code INTEGER NOT NULL,
                ip TEXT NOT NULL DEFAULT '',
                duration_ms INTEGER NOT NULL DEFAULT 0,
                error_code TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$t->webhooks()} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reseller_id INTEGER NOT NULL,
                event_name TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                target_url TEXT NOT NULL,
                payload_enc TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 5,
                next_attempt_at TEXT NOT NULL,
                status TEXT NOT NULL,
                last_response_code INTEGER NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL
            )",
        ];
    }
}
