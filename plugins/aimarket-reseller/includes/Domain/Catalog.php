<?php
/**
 * Product source. WordPress reads WooCommerce; tests use an in-memory catalog.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

interface Catalog
{
    public function get(int $id): ?Product;

    /**
     * @return array{items: list<Product>, total: int}
     */
    public function list(int $page, int $perPage, string $search = ''): array;
}
