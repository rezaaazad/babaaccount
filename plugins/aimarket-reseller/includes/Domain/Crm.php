<?php
/**
 * Reseller applications, tiers, and the figures the admin screens display.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class Crm
{
    public function __construct(
        private readonly Database $db,
        private readonly Tables $tables,
        private readonly Wallet $wallet
    ) {
    }

    public function seedDefaultTiers(): void
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM {$this->tables->tiers()}");
        if ((int) ($row['c'] ?? 0) > 0) {
            return;
        }
        $now = Clock::now();
        $this->db->insert(
            "INSERT INTO {$this->tables->tiers()} (slug, name, discount_percent, sort_order, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?)",
            ['bronze', 'برنزی', 5, 1, 1, $now]
        );
        $this->db->insert(
            "INSERT INTO {$this->tables->tiers()} (slug, name, discount_percent, sort_order, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?)",
            ['silver', 'نقره‌ای', 10, 2, 0, $now]
        );
        $this->db->insert(
            "INSERT INTO {$this->tables->tiers()} (slug, name, discount_percent, sort_order, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?)",
            ['gold', 'طلایی', 15, 3, 0, $now]
        );
    }

    public function apply(int $userId, string $name, string $phone, string $company, string $note): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new ApiException('invalid_name', 'نام همکار را وارد کنید.', 422);
        }
        $existing = $this->findByUser($userId);
        if ($existing !== null) {
            throw new ApiException('application_exists', 'برای این کاربر قبلاً درخواست همکاری ثبت شده است.', 409);
        }
        $now = Clock::now();
        return $this->db->insert(
            "INSERT INTO {$this->tables->resellers()}
            (user_id, status, tier_id, display_name, phone, company, note, created_at, updated_at)
            VALUES (?, 'pending', NULL, ?, ?, ?, ?, ?, ?)",
            [$userId, mb_substr($name, 0, 190), mb_substr($phone, 0, 40), mb_substr($company, 0, 190), $note, $now, $now]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUser(int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->tables->resellers()} WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->tables->resellers()} WHERE id = ?",
            [$id]
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(string $status, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $where = '1 = 1';
        $params = [];
        if ($status !== '') {
            $where .= ' AND r.status = ?';
            $params[] = $status;
        }
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM {$this->tables->resellers()} r WHERE {$where}",
            $params
        );
        $items = $this->db->fetchAll(
            "SELECT r.*, w.balance_minor, w.currency, t.name AS tier_name
             FROM {$this->tables->resellers()} r
             LEFT JOIN {$this->tables->wallets()} w ON w.reseller_id = r.id
             LEFT JOIN {$this->tables->tiers()} t ON t.id = r.tier_id
             WHERE {$where}
             ORDER BY r.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => $items, 'total' => (int) ($total['c'] ?? 0)];
    }

    public function approve(int $id, int $tierId, string $currency, Crypto $crypto): void
    {
        $tier = $this->db->fetchOne("SELECT id FROM {$this->tables->tiers()} WHERE id = ?", [$tierId]);
        if ($tier === null) {
            throw new ApiException('tier_missing', 'سطح انتخاب‌شده وجود ندارد.', 422);
        }
        $reseller = $this->find($id);
        if ($reseller === null) {
            throw new ApiException('reseller_not_found', 'همکار پیدا نشد.', 404);
        }
        $secret = $reseller['webhook_secret_enc'] ?: $crypto->encrypt('whsec_' . bin2hex(random_bytes(24)));
        $this->db->execute(
            "UPDATE {$this->tables->resellers()}
             SET status = 'approved', tier_id = ?, webhook_secret_enc = ?, updated_at = ?
             WHERE id = ?",
            [$tierId, $secret, Clock::now(), $id]
        );
        $this->wallet->open($id, $currency);
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['approved', 'suspended', 'rejected', 'pending'], true)) {
            throw new ApiException('invalid_status', 'وضعیت معتبر نیست.', 422);
        }
        $updated = $this->db->execute(
            "UPDATE {$this->tables->resellers()} SET status = ?, updated_at = ? WHERE id = ?",
            [$status, Clock::now(), $id]
        );
        if ($updated !== 1) {
            throw new ApiException('reseller_not_found', 'همکار پیدا نشد.', 404);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tiers(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->tables->tiers()} ORDER BY sort_order ASC, id ASC"
        );
    }

    public function updateTier(int $id, string $name, float $discount): void
    {
        $discount = Pricing::clampPercent($discount);
        $updated = $this->db->execute(
            "UPDATE {$this->tables->tiers()} SET name = ?, discount_percent = ? WHERE id = ?",
            [mb_substr(trim($name), 0, 80), $discount, $id]
        );
        if ($updated !== 1) {
            throw new ApiException('tier_missing', 'سطح پیدا نشد.', 404);
        }
    }

    public function savePrice(int $tierId, int $productId, ?int $priceMinor, ?float $discount): void
    {
        if ($priceMinor === null && $discount === null) {
            $this->db->execute(
                "DELETE FROM {$this->tables->tierPrices()} WHERE tier_id = ? AND product_id = ?",
                [$tierId, $productId]
            );
            return;
        }
        if ($discount !== null) {
            $discount = Pricing::clampPercent($discount);
        }
        if ($priceMinor !== null && $priceMinor < 0) {
            throw new ApiException('invalid_amount', 'قیمت عمده نمی‌تواند منفی باشد.', 422);
        }
        $existing = $this->db->fetchOne(
            "SELECT id FROM {$this->tables->tierPrices()} WHERE tier_id = ? AND product_id = ?",
            [$tierId, $productId]
        );
        if ($existing === null) {
            $this->db->insert(
                "INSERT INTO {$this->tables->tierPrices()} (tier_id, product_id, price_minor, discount_percent) VALUES (?, ?, ?, ?)",
                [$tierId, $productId, $priceMinor, $discount]
            );
            return;
        }
        $this->db->execute(
            "UPDATE {$this->tables->tierPrices()} SET price_minor = ?, discount_percent = ? WHERE id = ?",
            [$priceMinor, $discount, (int) $existing['id']]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pricesForTier(int $tierId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM {$this->tables->tierPrices()} WHERE tier_id = ?",
            [$tierId]
        );
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['product_id']] = $row;
        }
        return $indexed;
    }

    public function setWebhook(int $resellerId, string $url): void
    {
        $url = trim($url);
        if ($url !== '' && !self::validWebhook($url)) {
            throw new ApiException('invalid_webhook', 'آدرس وب‌هوک معتبر نیست.', 422);
        }
        $this->db->execute(
            "UPDATE {$this->tables->resellers()} SET webhook_url = ?, updated_at = ? WHERE id = ?",
            [$url === '' ? null : $url, Clock::now(), $resellerId]
        );
    }

    public static function validWebhook(string $url): bool
    {
        $parts = wp_parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        return ($scheme === 'https' || ($scheme === 'http' && apply_filters('hmr_webhook_allow_http', false)))
            && in_array((int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)), [80, 443], true)
            && (bool) wp_http_validate_url($url);
    }

    public function rotateWebhookSecret(int $resellerId, Crypto $crypto): string
    {
        $plain = 'whsec_' . bin2hex(random_bytes(24));
        $updated = $this->db->execute(
            "UPDATE {$this->tables->resellers()} SET webhook_secret_enc = ?, updated_at = ? WHERE id = ?",
            [$crypto->encrypt($plain), Clock::now(), $resellerId]
        );
        if ($updated !== 1) {
            throw new ApiException('reseller_not_found', 'همکار پیدا نشد.', 404);
        }
        return $plain;
    }

    public function revealSecret(array $reseller, Crypto $crypto): string
    {
        $stored = (string) ($reseller['webhook_secret_enc'] ?? '');
        if ($stored === '') {
            return '';
        }
        return $crypto->isOurs($stored) ? $crypto->decrypt($stored) : $stored;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function profit(): array
    {
        return $this->db->fetchAll(
            "SELECT r.id, r.display_name, r.status,
                COUNT(o.id) AS orders,
                COALESCE(SUM(o.total_minor), 0) AS revenue_minor,
                COALESCE(SUM(CASE WHEN o.unit_cost_minor IS NULL THEN 0 ELSE o.unit_cost_minor * o.quantity END), 0) AS cost_minor,
                COALESCE(SUM(CASE WHEN o.unit_cost_minor IS NULL THEN 1 ELSE 0 END), 0) AS unknown_cost_rows
             FROM {$this->tables->resellers()} r
             LEFT JOIN {$this->tables->orders()} o ON o.reseller_id = r.id AND o.status = 'delivered'
             GROUP BY r.id, r.display_name, r.status
             ORDER BY revenue_minor DESC"
        );
    }

    /**
     * @return array{requests_today: int, orders_today: int, spend_today: int, requests_by_day: list<array{day: string, count: int}>}
     */
    public function usage(int $resellerId): array
    {
        $today = gmdate('Y-m-d');
        $since = gmdate('Y-m-d H:i:s', time() - 8 * 86400);
        $requests = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM {$this->tables->apiLogs()}
             WHERE reseller_id = ? AND created_at >= ?",
            [$resellerId, $today . ' 00:00:00']
        );
        $orders = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM {$this->tables->orders()}
             WHERE reseller_id = ? AND created_at >= ?",
            [$resellerId, $today . ' 00:00:00']
        );
        $spend = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount_minor), 0) AS s FROM {$this->tables->ledger()}
             WHERE reseller_id = ? AND direction = 'debit' AND entry_type = 'purchase' AND created_at >= ?",
            [$resellerId, $today . ' 00:00:00']
        );
        $days = $this->db->fetchAll(
            "SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS c
             FROM {$this->tables->apiLogs()}
             WHERE reseller_id = ? AND created_at >= ?
             GROUP BY substr(created_at, 1, 10)
             ORDER BY day ASC",
            [$resellerId, $since]
        );
        $series = [];
        foreach ($days as $day) {
            $series[] = ['day' => (string) $day['day'], 'count' => (int) $day['c']];
        }
        return [
            'requests_today' => (int) ($requests['c'] ?? 0),
            'orders_today' => (int) ($orders['c'] ?? 0),
            'spend_today' => (int) ($spend['s'] ?? 0),
            'requests_by_day' => $series,
        ];
    }
}
