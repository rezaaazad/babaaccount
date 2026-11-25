<?php
/**
 * Objects shared by REST, admin, and the storefront dashboard.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\WordPress;

use HooshMarket\Reseller\Domain\Auth;
use HooshMarket\Reseller\Domain\Crm;
use HooshMarket\Reseller\Domain\Crypto;
use HooshMarket\Reseller\Domain\Database;
use HooshMarket\Reseller\Domain\OrderService;
use HooshMarket\Reseller\Domain\Pricing;
use HooshMarket\Reseller\Domain\RequestLog;
use HooshMarket\Reseller\Domain\Tables;
use HooshMarket\Reseller\Domain\Wallet;
use HooshMarket\Reseller\Domain\WebhookQueue;

final class Services
{
    public function __construct(
        public readonly Database $db,
        public readonly Tables $tables,
        public readonly Crypto $crypto,
        public readonly Wallet $wallet,
        public readonly Pricing $pricing,
        public readonly WooCatalog $catalog,
        public readonly AimarketFulfillment $fulfillment,
        public readonly OrderService $orders,
        public readonly Auth $auth,
        public readonly Crm $crm,
        public readonly WebhookQueue $webhooks,
        public readonly RequestLog $log,
        public readonly int $decimals,
        public readonly string $currency
    ) {
    }
}
