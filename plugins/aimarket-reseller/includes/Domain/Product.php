<?php
/**
 * A sellable WooCommerce product as the reseller API sees it.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class Product
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $sku,
        public readonly int $retailMinor,
        public readonly ?int $costMinor,
        public readonly string $currency,
        public readonly int $decimals
    ) {
    }
}
