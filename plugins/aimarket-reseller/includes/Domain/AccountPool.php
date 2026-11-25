<?php
/**
 * Claims rows from an account-inventory table.
 *
 * aimarket-accounts is expected to expose product_id, status, and a password
 * column. The WordPress bridge maps real column names onto this pool. Partial
 * claims are reverted before an exception leaves this class, so a failed order
 * cannot burn stock.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class AccountPool implements Fulfillment
{
    /**
     * @param array{id?: string, product_id?: string, status?: string, username?: string, password?: string, extra?: string, order_id?: string} $columns
     */
    public function __construct(
        private readonly Database $db,
        private readonly string $table,
        private readonly array $columns = [],
        private readonly string $available = 'available',
        private readonly string $sold = 'sold'
    ) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Unsafe account table name.');
        }
    }

    public function assertReady(): void
    {
        if (!$this->db->tableExists($this->table)) {
            throw new ApiException(
                'fulfillment_unavailable',
                'جدول موجودی اکانت پیدا نشد. اتصال aimarket-accounts را در تنظیمات همکاری بررسی کنید.',
                503
            );
        }
        foreach (['product_id', 'status', 'password'] as $required) {
            $this->column($required);
        }
    }

    public function stock(int $productId): int
    {
        $this->assertReady();
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM {$this->table} WHERE {$this->column('product_id')} = ? AND {$this->column('status')} = ?",
            [$productId, $this->available]
        );
        return (int) ($row['c'] ?? 0);
    }

    public function claim(int $productId, int $qty, int $orderId): array
    {
        $this->assertReady();
        if ($qty < 1) {
            throw new ApiException('invalid_quantity', 'تعداد سفارش معتبر نیست.', 422);
        }
        $idCol = $this->column('id');
        $userCol = $this->has('username') ? $this->column('username') : null;
        $passCol = $this->column('password');
        $extraCol = $this->has('extra') ? $this->column('extra') : null;
        $statusCol = $this->column('status');
        $productCol = $this->column('product_id');
        $select = $idCol . ' AS id, ' . $passCol . ' AS password';
        $select .= $userCol !== null ? ", {$userCol} AS username" : ", '' AS username";
        $select .= $extraCol !== null ? ", {$extraCol} AS extra" : ", '' AS extra";
        $rows = $this->db->fetchAll(
            "SELECT {$select} FROM {$this->table}
             WHERE {$productCol} = ? AND {$statusCol} = ?
             ORDER BY {$idCol} ASC
             LIMIT {$qty}",
            [$productId, $this->available]
        );
        if (count($rows) < $qty) {
            throw new ApiException('out_of_stock', 'موجودی این محصول برای همکاری کافی نیست.', 409);
        }
        $claimed = [];
        $orderCol = $this->has('order_id') ? $this->column('order_id') : null;
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            if ($orderCol !== null) {
                $updated = $this->db->execute(
                    "UPDATE {$this->table}
                     SET {$statusCol} = ?, {$orderCol} = ?
                     WHERE {$idCol} = ? AND {$statusCol} = ?",
                    [$this->sold, $orderId, $id, $this->available]
                );
            } else {
                $updated = $this->db->execute(
                    "UPDATE {$this->table} SET {$statusCol} = ? WHERE {$idCol} = ? AND {$statusCol} = ?",
                    [$this->sold, $id, $this->available]
                );
            }
            if ($updated === 1) {
                $claimed[] = [
                    'id' => $id,
                    'username' => (string) ($row['username'] ?? ''),
                    'password' => (string) ($row['password'] ?? ''),
                    'extra' => (string) ($row['extra'] ?? ''),
                ];
            }
        }
        if (count($claimed) < $qty) {
            foreach ($claimed as $item) {
                if ($orderCol !== null) {
                    $this->db->execute(
                        "UPDATE {$this->table} SET {$statusCol} = ?, {$orderCol} = NULL WHERE {$idCol} = ?",
                        [$this->available, $item['id']]
                    );
                } else {
                    $this->db->execute(
                        "UPDATE {$this->table} SET {$statusCol} = ? WHERE {$idCol} = ?",
                        [$this->available, $item['id']]
                    );
                }
            }
            throw new ApiException('out_of_stock', 'موجودی این محصول برای همکاری کافی نیست.', 409);
        }
        return $claimed;
    }

    private function has(string $logical): bool
    {
        if (!array_key_exists($logical, $this->columns)) {
            return true;
        }
        return $this->columns[$logical] !== '' && $this->columns[$logical] !== null;
    }

    private function column(string $logical): string
    {
        $name = $this->columns[$logical] ?? $logical;
        if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new ApiException('fulfillment_unavailable', 'نگاشت ستون‌های موجودی اکانت معتبر نیست.', 503);
        }
        return $name;
    }
}
