<?php
/**
 * HMAC-signed webhooks with a stored payload and a retry schedule.
 *
 * Signature header value is "sha256=" plus hex HMAC-SHA256 of the raw body.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class WebhookQueue
{
    /** @var list<int> */
    private const BACKOFF_MINUTES = [1, 5, 15, 60, 360];

    /**
     * @param callable(string, string, array<string, string>): array{code: int, error: string}|null $sender
     */
    public function __construct(
        private readonly Database $db,
        private readonly Tables $tables,
        private readonly Crypto $crypto,
        private readonly mixed $sender = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function enqueue(int $resellerId, string $event, int $orderId, array $data): ?int
    {
        if ($event !== 'order.delivered' && $event !== 'order.failed') {
            throw new \InvalidArgumentException('Unknown webhook event.');
        }
        $existing = $this->db->fetchOne(
            "SELECT id FROM {$this->tables->webhooks()}
             WHERE order_id = ? AND event_name = ? AND status IN ('pending', 'delivered')
             LIMIT 1",
            [$orderId, $event]
        );
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $reseller = $this->db->fetchOne(
            "SELECT webhook_url, webhook_secret_enc FROM {$this->tables->resellers()} WHERE id = ?",
            [$resellerId]
        );
        $url = trim((string) ($reseller['webhook_url'] ?? ''));
        $secretEnc = (string) ($reseller['webhook_secret_enc'] ?? '');
        if ($reseller === null || $url === '' || $secretEnc === '') {
            return null;
        }
        $body = json_encode([
            'id' => 'evt_pending',
            'type' => $event,
            'created_at' => Clock::iso(Clock::now()),
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $id = $this->db->insert(
            "INSERT INTO {$this->tables->webhooks()}
            (reseller_id, event_name, order_id, target_url, payload_enc, attempts, max_attempts, next_attempt_at, status, created_at)
            VALUES (?, ?, ?, ?, ?, 0, 5, ?, 'pending', ?)",
            [$resellerId, $event, $orderId, $url, $this->crypto->encrypt($body), Clock::now(), Clock::now()]
        );
        $payload = json_encode([
            'id' => 'evt_' . $id,
            'type' => $event,
            'created_at' => Clock::iso(Clock::now()),
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->db->execute(
            "UPDATE {$this->tables->webhooks()} SET payload_enc = ? WHERE id = ?",
            [$this->crypto->encrypt($payload), $id]
        );
        return $id;
    }

    public function resend(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->tables->webhooks()}
             SET status = 'pending', attempts = 0, next_attempt_at = ?, last_error = NULL
             WHERE id = ?",
            [Clock::now(), $id]
        );
    }

    public function dispatchDue(int $limit = 20): int
    {
        $limit = max(1, min(50, $limit));
        $rows = $this->db->fetchAll(
            "SELECT * FROM {$this->tables->webhooks()}
             WHERE status = 'pending' AND next_attempt_at <= ?
             ORDER BY id ASC
             LIMIT {$limit}",
            [Clock::now()]
        );
        $sent = 0;
        foreach ($rows as $row) {
            $this->deliver($row);
            $sent++;
        }
        return $sent;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function deliver(array $row): void
    {
        $reseller = $this->db->fetchOne(
            "SELECT webhook_secret_enc FROM {$this->tables->resellers()} WHERE id = ?",
            [(int) $row['reseller_id']]
        );
        $attempts = (int) $row['attempts'] + 1;
        try {
            $body = $this->crypto->decrypt((string) $row['payload_enc']);
            $secret = $this->crypto->decrypt((string) ($reseller['webhook_secret_enc'] ?? ''));
            $signature = self::sign($body, $secret);
            $result = $this->send((string) $row['target_url'], $body, [
                'Content-Type' => 'application/json',
                'User-Agent' => 'HooshMarket-Reseller/1.0',
                'X-HooshMarket-Event' => (string) $row['event_name'],
                'X-HooshMarket-Delivery' => (string) $row['id'],
                'X-HooshMarket-Signature' => $signature,
            ]);
            $code = $result['code'];
            $error = $result['error'];
        } catch (\Throwable $e) {
            $code = 0;
            $error = $e->getMessage();
        }
        $ok = $code >= 200 && $code < 300;
        if ($ok) {
            $this->db->execute(
                "UPDATE {$this->tables->webhooks()}
                 SET attempts = ?, status = 'delivered', last_response_code = ?, last_error = NULL
                 WHERE id = ?",
                [$attempts, $code, (int) $row['id']]
            );
            return;
        }
        $dead = $attempts >= (int) $row['max_attempts'];
        $delay = self::BACKOFF_MINUTES[min($attempts - 1, count(self::BACKOFF_MINUTES) - 1)];
        $this->db->execute(
            "UPDATE {$this->tables->webhooks()}
             SET attempts = ?, status = ?, next_attempt_at = ?, last_response_code = ?, last_error = ?
             WHERE id = ?",
            [
                $attempts,
                $dead ? 'failed' : 'pending',
                Clock::plusMinutes(Clock::now(), $delay),
                $code > 0 ? $code : null,
                mb_substr($error, 0, 500),
                (int) $row['id'],
            ]
        );
    }

    /**
     * @param array<string, string> $headers
     * @return array{code: int, error: string}
     */
    private function send(string $url, string $body, array $headers): array
    {
        if ($this->sender !== null) {
            return ($this->sender)($url, $body, $headers);
        }
        throw new \RuntimeException('No webhook sender is configured.');
    }

    public static function sign(string $body, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }
}
