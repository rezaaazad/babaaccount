<?php
/**
 * Claims accounts through AIM_Delivery while the reseller wallet transaction
 * is still open on $wpdb. Credentials stay encrypted with AIM_Crypto (`v1:`).
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\WordPress;

use HooshMarket\Reseller\Domain\ApiException;
use HooshMarket\Reseller\Domain\Database;
use HooshMarket\Reseller\Domain\Fulfillment;

final class AimarketFulfillment implements Fulfillment
{
    public function __construct(private readonly Database $db)
    {
    }

    private int $customerId = 0;

    /** @var array{0: int, 1: int}|null */
    private ?array $pendingStock = null;

    private ?\WC_Order $pendingOrder = null;

    public function setCustomer(int $userId): void
    {
        $this->customerId = $userId;
    }

    public function assertReady(): void
    {
        if (!class_exists('AIM_Inventory') || !class_exists('AIM_Delivery') || !class_exists('AIM_Crypto')) {
            throw new ApiException(
                'fulfillment_unavailable',
                'ماژول فروش اکانت (aimarket-accounts) فعال نیست.',
                503
            );
        }
    }

    public function stock(int $productId): int
    {
        $this->assertReady();
        [$parent, $variation] = $this->target($productId);
        return \AIM_Inventory::available_count($parent, $variation);
    }

    public function claim(int $productId, int $qty, int $orderId): array
    {
        $this->assertReady();
        if ($this->customerId <= 0) {
            throw new ApiException('reseller_not_approved', 'سفارش همکاری به کاربر وردپرس وصل نیست.', 409);
        }
        if (!$this->dbInTransaction()) {
            throw new ApiException(
                'fulfillment_unavailable',
                'برداشت از انبار فقط داخل تراکنش کیف پول انجام می‌شود.',
                503
            );
        }
        [$parent, $variation] = $this->target($productId);
        if (\AIM_Inventory::delivery_mode($parent) !== 'auto') {
            throw new ApiException('not_auto_delivery', 'این محصول تحویل خودکار از انبار ندارد.', 409);
        }
        $product = wc_get_product($variation ?: $parent);
        if (!$product instanceof \WC_Product) {
            throw new ApiException('product_not_found', 'محصول پیدا نشد یا برای فروش همکاری فعال نیست.', 404);
        }

        $order = wc_create_order([
            'customer_id' => $this->customerId,
            'status' => 'pending',
        ]);
        if (!$order instanceof \WC_Order) {
            throw new ApiException('fulfillment_unavailable', 'ساخت سفارش ووکامرس برای تحویل انجام نشد.', 503);
        }
        $order->set_payment_method('hmr_wallet');
        $order->set_payment_method_title('کیف پول همکاری');
        $itemId = $order->add_product($product, $qty);
        if (!$itemId) {
            throw new ApiException('fulfillment_unavailable', 'افزودن محصول به سفارش ووکامرس انجام نشد.', 503);
        }
        $order->calculate_totals(false);
        $order->update_meta_data('_hmr_order_id', $orderId);
        $order->save();

        $ids = \AIM_Delivery::claim($parent, $variation, $qty, $order, (int) $itemId);
        if (count($ids) < $qty) {
            throw new ApiException('out_of_stock', 'موجودی این محصول برای همکاری کافی نیست.', 409);
        }
        $item = $order->get_item($itemId);
        if ($item instanceof \WC_Order_Item_Product) {
            $item->update_meta_data('_aim_delivered', count($ids));
            $item->update_meta_data('_aim_shortage', 0);
            $item->save();
        }

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'hmr_orders', ['wc_order_id' => $order->get_id()], ['id' => $orderId]);

        $out = [];
        foreach ($ids as $id) {
            $row = \AIM_Inventory::get($id);
            if (!$row) {
                throw new ApiException('delivery_failed', 'اکانت رزرو شده در انبار پیدا نشد.', 503);
            }
            $plain = \AIM_Inventory::secret($row);
            $out[] = [
                'id' => (string) $row->id,
                'username' => '',
                'password' => $plain,
                'extra' => (string) $row->note,
                'secret' => $plain,
                'aim_secret' => (string) $row->secret,
            ];
        }
        $this->pendingOrder = $order;
        $this->pendingStock = [$parent, $variation];
        return $out;
    }

    /** Runs only after the wallet transaction has committed. */
    public function afterCommit(): void
    {
        if ($this->pendingOrder instanceof \WC_Order) {
            $this->pendingOrder->update_status('completed', 'تحویل از همکاری در فروش.');
        }
        if ($this->pendingStock !== null && class_exists('AIM_Inventory')) {
            \AIM_Inventory::sync_stock($this->pendingStock[0], $this->pendingStock[1]);
        }
        $this->pendingOrder = null;
        $this->pendingStock = null;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function target(int $productId): array
    {
        $product = wc_get_product($productId);
        if (!$product instanceof \WC_Product) {
            throw new ApiException('product_not_found', 'محصول پیدا نشد یا برای فروش همکاری فعال نیست.', 404);
        }
        $parent = $product->get_parent_id() ?: $product->get_id();
        $variation = $product->get_parent_id() ? $product->get_id() : 0;
        return [$parent, $variation];
    }

    private function dbInTransaction(): bool
    {
        return $this->db->inTransaction();
    }
}
