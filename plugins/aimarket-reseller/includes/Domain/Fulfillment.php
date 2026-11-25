<?php
/**
 * Account inventory. The WordPress bridge points this at aimarket-accounts.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

interface Fulfillment
{
    public function assertReady(): void;

    public function stock(int $productId): int;

    /**
     * Claim $qty available accounts. Must run inside the caller's transaction
     * when the inventory lives in the same database, and must leave no rows
     * marked sold if it throws.
     *
     * @return list<array{id: string, username: string, password: string, extra: string}>
     */
    public function claim(int $productId, int $qty, int $orderId): array;
}
