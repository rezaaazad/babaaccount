<?php
/**
 * Physical table names. The prefix is the WordPress table prefix.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class Tables
{
    public function __construct(private readonly string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
            throw new \InvalidArgumentException('Unsafe table prefix.');
        }
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function resellers(): string
    {
        return $this->prefix . 'hmr_resellers';
    }

    public function tiers(): string
    {
        return $this->prefix . 'hmr_tiers';
    }

    public function tierPrices(): string
    {
        return $this->prefix . 'hmr_tier_prices';
    }

    public function wallets(): string
    {
        return $this->prefix . 'hmr_wallets';
    }

    public function ledger(): string
    {
        return $this->prefix . 'hmr_ledger';
    }

    public function apiKeys(): string
    {
        return $this->prefix . 'hmr_api_keys';
    }

    public function rateBuckets(): string
    {
        return $this->prefix . 'hmr_rate_buckets';
    }

    public function idempotency(): string
    {
        return $this->prefix . 'hmr_idempotency';
    }

    public function orders(): string
    {
        return $this->prefix . 'hmr_orders';
    }

    public function deliveries(): string
    {
        return $this->prefix . 'hmr_deliveries';
    }

    public function apiLogs(): string
    {
        return $this->prefix . 'hmr_api_logs';
    }

    public function webhooks(): string
    {
        return $this->prefix . 'hmr_webhooks';
    }
}
