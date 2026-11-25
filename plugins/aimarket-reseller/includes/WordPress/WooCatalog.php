<?php
/**
 * Reads published WooCommerce products, skipping wallet top-ups and hidden ones.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\WordPress;

use HooshMarket\Reseller\Domain\Catalog;
use HooshMarket\Reseller\Domain\Money;
use HooshMarket\Reseller\Domain\Product;

final class WooCatalog implements Catalog
{
    public function __construct(private readonly int $decimals, private readonly string $currency)
    {
    }

    public function get(int $id): ?Product
    {
        if (!function_exists('wc_get_product') || !class_exists('AIM_Inventory')) {
            return null;
        }
        $product = wc_get_product($id);
        if (!$product instanceof \WC_Product) {
            return null;
        }
        $parent = $product->get_parent_id() ?: $product->get_id();
        if (\AIM_Inventory::delivery_mode($parent) !== 'auto') {
            return null;
        }
        return $this->map($product);
    }

    public function list(int $page, int $perPage, string $search = ''): array
    {
        if (!class_exists('AIM_Inventory') || !function_exists('wc_get_product')) {
            return ['items' => [], 'total' => 0];
        }
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $mapped = [];
        foreach (\AIM_Inventory::targets() as $target) {
            if (($target['mode'] ?? '') !== 'auto') {
                continue;
            }
            $id = (int) ($target['variation_id'] ?: $target['product_id']);
            $product = wc_get_product($id);
            if (!$product instanceof \WC_Product || $product->get_status() !== 'publish') {
                $parent = wc_get_product((int) $target['product_id']);
                if ($target['variation_id'] && $parent instanceof \WC_Product && $parent->get_status() === 'publish' && $product instanceof \WC_Product) {
                    // Variations do not have their own publish status.
                } else {
                    continue;
                }
            }
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $item = $this->map($product);
            if ($item === null) {
                continue;
            }
            if ($search !== '' && !str_contains(mb_strtolower($item->name . ' ' . $item->sku), mb_strtolower($search))) {
                continue;
            }
            $mapped[] = $item;
        }
        $total = count($mapped);
        $offset = ($page - 1) * $perPage;
        return ['items' => array_slice($mapped, $offset, $perPage), 'total' => $total];
    }

    private function map(\WC_Product $product): ?Product
    {
        if ($product->get_status() !== 'publish') {
            return null;
        }
        if ((string) $product->get_meta('_hmr_wallet_topup') === 'yes') {
            return null;
        }
        if ((string) $product->get_meta('_hmr_api_hidden') === 'yes') {
            return null;
        }
        if (apply_filters('hm_reseller_include_product', true, $product) !== true) {
            return null;
        }
        $costRaw = (string) ($product->get_meta('_hmr_cost') ?: $product->get_meta('_aimarket_cost'));
        $cost = null;
        if ($costRaw !== '') {
            try {
                $cost = Money::majorToMinor($costRaw, $this->decimals);
            } catch (\Throwable) {
                $cost = null;
            }
        }
        $rawPrice = (string) $product->get_price();
        $retail = Money::majorToMinor($rawPrice === '' ? '0' : $rawPrice, $this->decimals);
        return new Product(
            $product->get_id(),
            $product->get_name(),
            (string) $product->get_sku(),
            $retail,
            $cost,
            $this->currency,
            $this->decimals
        );
    }
}
