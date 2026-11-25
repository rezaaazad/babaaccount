<?php
/**
 * Places a reseller order inside one database transaction.
 *
 * The idempotency row is inserted before the wallet moves. Insufficient funds
 * rolls the key back so the same key can be reused after a top-up. A delivery
 * failure keeps the order, refunds the wallet, and is safe to replay.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class OrderService
{
    public function __construct(
        private readonly Database $db,
        private readonly Tables $tables,
        private readonly Wallet $wallet,
        private readonly Pricing $pricing,
        private readonly Catalog $catalog,
        private readonly Fulfillment $fulfillment,
        private readonly Crypto $crypto,
        private readonly int $maxQuantity = 20
    ) {
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>, event: string}
     */
    public function place(int $resellerId, int $productId, int $quantity, string $reference, string $idempotencyKey): array
    {
        $this->assertIdempotencyKey($idempotencyKey);
        if ($quantity < 1 || $quantity > $this->maxQuantity) {
            throw new ApiException('invalid_quantity', 'تعداد سفارش خارج از محدودهٔ مجاز است.', 422);
        }
        $reference = mb_substr(trim($reference), 0, 120);
        $hash = self::requestHash($productId, $quantity, $reference);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $replay = $this->readCompleted($resellerId, $idempotencyKey, $hash);
            if ($replay !== null) {
                return $replay;
            }
            $this->db->begin();
            try {
                try {
                    $this->db->insert(
                        "INSERT INTO {$this->tables->idempotency()}
                        (reseller_id, idem_key, request_hash, status, response_code, resource_id, created_at, updated_at)
                        VALUES (?, ?, ?, 'pending', 0, NULL, ?, ?)",
                        [$resellerId, $idempotencyKey, $hash, Clock::now(), Clock::now()]
                    );
                } catch (\Throwable $e) {
                    $this->db->rollback();
                    if (!$this->db->isUniqueViolation($e)) {
                        throw $e;
                    }
                    continue;
                }
                $result = $this->placeLocked($resellerId, $productId, $quantity, $reference, $idempotencyKey);
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollback();
                throw $e;
            }
            if (method_exists($this->fulfillment, 'afterCommit')) {
                try {
                    $this->fulfillment->afterCommit();
                } catch (\Throwable $e) {
                    error_log('hm-reseller afterCommit ' . $e->getMessage());
                }
            }
            return $result;
        }
        $replay = $this->readCompleted($resellerId, $idempotencyKey, $hash);
        if ($replay !== null) {
            return $replay;
        }
        throw new ApiException('request_in_progress', 'درخواست مشابه هنوز در حال پردازش است.', 409);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $resellerId, int $orderId): array
    {
        $order = $this->findOwned($resellerId, $orderId);
        if ($order === null) {
            throw new ApiException('order_not_found', 'سفارش پیدا نشد.', 404);
        }
        return $this->present($order);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(int $resellerId, int $page, int $perPage, string $status = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $where = 'reseller_id = ?';
        $params = [$resellerId];
        if ($status !== '') {
            $where .= ' AND status = ?';
            $params[] = $status;
        }
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM {$this->tables->orders()} WHERE {$where}",
            $params
        );
        $rows = $this->db->fetchAll(
            "SELECT * FROM {$this->tables->orders()} WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $items = [];
        foreach ($rows as $row) {
            $presented = $this->present($row);
            unset($presented['credentials']);
            $items[] = $presented;
        }
        return ['items' => $items, 'total' => (int) ($total['c'] ?? 0)];
    }

    public static function requestHash(int $productId, int $quantity, string $reference): string
    {
        return hash('sha256', $productId . '|' . $quantity . '|' . $reference);
    }

    /**
     * Runs after the idempotency row is inserted and the transaction is open.
     *
     * @return array{http_status: int, body: array<string, mixed>, event: string}
     */
    private function placeLocked(int $resellerId, int $productId, int $quantity, string $reference, string $idempotencyKey): array
    {
        $reseller = $this->db->fetchOne(
            "SELECT * FROM {$this->tables->resellers()} WHERE id = ?",
            [$resellerId]
        );
        if ($reseller === null || $reseller['status'] !== 'approved') {
            throw new ApiException('reseller_not_approved', 'حساب همکاری اجازهٔ خرید ندارد.', 403);
        }
        if ($reseller['tier_id'] === null || (int) $reseller['tier_id'] <= 0) {
            throw new ApiException('tier_missing', 'برای این همکار سطحی تعیین نشده است.', 409);
        }
        $product = $this->catalog->get($productId);
        if ($product === null) {
            throw new ApiException('product_not_found', 'محصول پیدا نشد یا برای فروش همکاری فعال نیست.', 404);
        }
        $wallet = $this->wallet->balance($resellerId);
        if ($wallet === null) {
            throw new ApiException('wallet_missing', 'کیف پول این همکار ساخته نشده است.', 409);
        }
        if (strtoupper($product->currency) !== strtoupper((string) $wallet['currency'])) {
            throw new ApiException('currency_mismatch', 'ارز محصول با ارز کیف پول یکی نیست.', 409);
        }
        $this->fulfillment->assertReady();
        $quote = $this->pricing->quote((int) $reseller['tier_id'], $product);
        $total = $quote->priceMinor * $quantity;
        if ($total < 0) {
            throw new ApiException('invalid_amount', 'مبلغ سفارش معتبر نیست.', 422);
        }
        $now = Clock::now();
        $orderId = $this->db->insert(
            "INSERT INTO {$this->tables->orders()}
            (reseller_id, product_id, quantity, unit_price_minor, unit_cost_minor, total_minor, currency, status, client_reference, error_code, error_message, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'delivering', ?, '', '', ?, ?)",
            [
                $resellerId,
                $product->id,
                $quantity,
                $quote->priceMinor,
                $product->costMinor,
                $total,
                strtoupper($product->currency),
                $reference,
                $now,
                $now,
            ]
        );
        $ledgerId = null;
        if ($total > 0) {
            $moved = $this->wallet->apply(
                $resellerId,
                'debit',
                $total,
                'purchase',
                'order',
                (string) $orderId,
                'خرید همکاری #' . $orderId,
                null
            );
            $ledgerId = $moved['ledger_id'];
        }
        // A short or failed claim must roll the whole transaction back, including
        // any rows AIM_Delivery::claim already marked assigned. Do not commit a
        // refund beside a claim that may have landed on another connection.
        $credentials = $this->fulfillment->claim($product->id, $quantity, $orderId);
        foreach ($credentials as $credential) {
            $this->db->insert(
                "INSERT INTO {$this->tables->deliveries()} (order_id, account_ref, payload_enc, created_at) VALUES (?, ?, ?, ?)",
                [
                    $orderId,
                    $credential['id'],
                    $this->payload($credential),
                    Clock::now(),
                ]
            );
        }
        $this->db->execute(
            "UPDATE {$this->tables->orders()} SET status = 'delivered', ledger_id = ?, updated_at = ? WHERE id = ?",
            [$ledgerId, Clock::now(), $orderId]
        );
        $this->completeIdempotency($resellerId, $idempotencyKey, $orderId, 201);
        $order = $this->mustFind($resellerId, $orderId);
        return [
            'http_status' => 201,
            'body' => $this->present($order),
            'event' => 'order.delivered',
        ];
    }

    private function completeIdempotency(int $resellerId, string $key, int $orderId, int $status): void
    {
        $updated = $this->db->execute(
            "UPDATE {$this->tables->idempotency()}
             SET status = 'completed', response_code = ?, resource_id = ?, updated_at = ?
             WHERE reseller_id = ? AND idem_key = ? AND status = 'pending'",
            [$status, $orderId, Clock::now(), $resellerId, $key]
        );
        if ($updated !== 1) {
            throw new \RuntimeException('Idempotency row was not completed.');
        }
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>, event: string, replayed?: bool}|null
     */
    private function readCompleted(int $resellerId, string $key, string $hash): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM {$this->tables->idempotency()} WHERE reseller_id = ? AND idem_key = ?",
            [$resellerId, $key]
        );
        if ($row === null) {
            return null;
        }
        if (!hash_equals((string) $row['request_hash'], $hash)) {
            throw new ApiException('idempotency_conflict', 'این کلید تکرار قبلاً با بدنهٔ دیگری ثبت شده است.', 409);
        }
        if ($row['status'] !== 'completed' || $row['resource_id'] === null) {
            throw new ApiException('request_in_progress', 'درخواست مشابه هنوز در حال پردازش است.', 409);
        }
        $order = $this->findOwned($resellerId, (int) $row['resource_id']);
        if ($order === null) {
            throw new ApiException('order_not_found', 'سفارش این کلید تکرار پیدا نشد.', 404);
        }
        $event = $order['status'] === 'delivered' ? 'order.delivered' : 'order.failed';
        return [
            'http_status' => (int) $row['response_code'],
            'body' => $this->present($order),
            'event' => $event,
            'replayed' => true,
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function present(array $order): array
    {
        return [
            'id' => (int) $order['id'],
            'status' => (string) $order['status'],
            'product_id' => (int) $order['product_id'],
            'quantity' => (int) $order['quantity'],
            'currency' => (string) $order['currency'],
            'unit_price' => (int) $order['unit_price_minor'],
            'total' => (int) $order['total_minor'],
            'reference' => (string) $order['client_reference'],
            'error_code' => $order['error_code'] !== '' && $order['error_code'] !== null ? (string) $order['error_code'] : null,
            'credentials' => $this->credentials((int) $order['id']),
            'created_at' => Clock::iso((string) $order['created_at']),
        ];
    }

    /**
     * @return list<array{id: string, username: string, password: string, extra: string}>
     */
    private function credentials(int $orderId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT account_ref, payload_enc FROM {$this->tables->deliveries()} WHERE order_id = ? ORDER BY id ASC",
            [$orderId]
        );
        $out = [];
        foreach ($rows as $row) {
            $stored = (string) $row['payload_enc'];
            if (str_starts_with($stored, 'v1:')) {
                $plain = class_exists('AIM_Crypto') ? \AIM_Crypto::decrypt($stored) : $stored;
                $out[] = [
                    'id' => (string) $row['account_ref'],
                    'username' => '',
                    'password' => $plain,
                    'extra' => '',
                    'secret' => $plain,
                ];
                continue;
            }
            $json = $this->crypto->decrypt($stored);
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }
            $out[] = [
                'id' => (string) ($data['id'] ?? $row['account_ref']),
                'username' => (string) ($data['username'] ?? ''),
                'password' => (string) ($data['password'] ?? ''),
                'extra' => (string) ($data['extra'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * aimarket-accounts already stores the credential as AES-256-GCM (`v1:`).
     * Keep that ciphertext as-is. Anything else is a test double and is wrapped
     * with this plugin's key.
     *
     * @param array<string, mixed> $credential
     */
    private function payload(array $credential): string
    {
        $aim = $credential['aim_secret'] ?? null;
        if (is_string($aim) && str_starts_with($aim, 'v1:')) {
            return $aim;
        }
        unset($credential['aim_secret'], $credential['wc_order_id']);
        return $this->crypto->encrypt(json_encode($credential, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOwned(int $resellerId, int $orderId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->tables->orders()} WHERE id = ? AND reseller_id = ?",
            [$orderId, $resellerId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mustFind(int $resellerId, int $orderId): array
    {
        $order = $this->findOwned($resellerId, $orderId);
        if ($order === null) {
            throw new \RuntimeException('Order disappeared inside its own transaction.');
        }
        return $order;
    }

    private function assertIdempotencyKey(string $key): void
    {
        $len = strlen($key);
        if ($len < 8 || $len > 128 || !preg_match('/^[A-Za-z0-9._:-]+$/', $key)) {
            throw new ApiException(
                'invalid_idempotency_key',
                'هدر Idempotency-Key الزامی است و باید ۸ تا ۱۲۸ نویسه از حروف، عدد، نقطه، خط تیره یا دونقطه باشد.',
                400
            );
        }
    }
}
