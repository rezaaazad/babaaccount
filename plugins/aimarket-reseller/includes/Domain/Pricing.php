<?php
/**
 * Wholesale price for a reseller tier.
 *
 * A per-product fixed price wins. Otherwise a per-product discount wins.
 * Otherwise the tier's own discount is applied to the retail price.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class Quote
{
    public function __construct(
        public readonly int $retailMinor,
        public readonly int $priceMinor,
        public readonly float $discountPercent
    ) {
    }
}

final class Pricing
{
    public function __construct(private readonly Database $db, private readonly Tables $tables)
    {
    }

    public function quote(?int $tierId, Product $product): Quote
    {
        $retail = $product->retailMinor;
        $override = null;
        if ($tierId !== null && $tierId > 0) {
            $override = $this->db->fetchOne(
                "SELECT price_minor, discount_percent FROM {$this->tables->tierPrices()} WHERE tier_id = ? AND product_id = ?",
                [$tierId, $product->id]
            );
        }
        if ($override !== null && $override['price_minor'] !== null && $override['price_minor'] !== '') {
            $price = max(0, (int) $override['price_minor']);
            $percent = $retail > 0 ? round((1 - ($price / $retail)) * 100, 2) : 0.0;
            return new Quote($retail, $price, $percent);
        }
        $percent = $this->resolvePercent($tierId, $override);
        $price = self::discount($retail, $percent);
        return new Quote($retail, $price, $percent);
    }

    /**
     * @param array<string, mixed>|null $override
     */
    private function resolvePercent(?int $tierId, ?array $override): float
    {
        if ($override !== null && $override['discount_percent'] !== null && $override['discount_percent'] !== '') {
            return self::clampPercent((float) $override['discount_percent']);
        }
        if ($tierId === null || $tierId <= 0) {
            return 0.0;
        }
        $tier = $this->db->fetchOne(
            "SELECT discount_percent FROM {$this->tables->tiers()} WHERE id = ?",
            [$tierId]
        );
        if ($tier === null) {
            return 0.0;
        }
        return self::clampPercent((float) $tier['discount_percent']);
    }

    public static function discount(int $retailMinor, float $percent): int
    {
        $percent = self::clampPercent($percent);
        $bps = (int) round($percent * 100);
        $keep = 10000 - $bps;
        if ($retailMinor <= 0 || $keep <= 0) {
            return 0;
        }
        return intdiv($retailMinor * $keep, 10000);
    }

    public static function clampPercent(float $percent): float
    {
        if ($percent < 0) {
            return 0.0;
        }
        if ($percent > 100) {
            return 100.0;
        }
        return $percent;
    }
}
