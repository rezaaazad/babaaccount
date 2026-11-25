<?php
/**
 * API keys are stored as SHA-256. The plaintext is shown once.
 * Each key has an optional IP allowlist and a per-minute rate limit.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class AuthContext
{
    /**
     * @param array<string, mixed> $reseller
     */
    public function __construct(
        public readonly int $resellerId,
        public readonly int $keyId,
        public readonly int $rateLimit,
        public readonly array $reseller
    ) {
    }
}

final class Auth
{
    public function __construct(private readonly Database $db, private readonly Tables $tables)
    {
    }

    /**
     * @param list<string> $ipAllowlist
     */
    public function issue(int $resellerId, string $label, array $ipAllowlist, int $rateLimit): string
    {
        $rateLimit = max(1, min(10000, $rateLimit));
        $token = 'hm_live_' . bin2hex(random_bytes(20));
        $cleanIps = [];
        foreach ($ipAllowlist as $ip) {
            $ip = trim($ip);
            if ($ip !== '') {
                $cleanIps[] = $ip;
            }
        }
        $this->db->insert(
            "INSERT INTO {$this->tables->apiKeys()}
            (reseller_id, label, key_prefix, key_hash, ip_allowlist, rate_limit, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $resellerId,
                mb_substr($label, 0, 80),
                substr($token, 0, 16),
                hash('sha256', $token),
                json_encode(array_values($cleanIps), JSON_UNESCAPED_UNICODE),
                $rateLimit,
                Clock::now(),
            ]
        );
        return $token;
    }

    public function revoke(int $resellerId, int $keyId): void
    {
        $updated = $this->db->execute(
            "UPDATE {$this->tables->apiKeys()}
             SET revoked_at = ?
             WHERE id = ? AND reseller_id = ? AND revoked_at IS NULL",
            [Clock::now(), $keyId, $resellerId]
        );
        if ($updated !== 1) {
            throw new ApiException('key_not_found', 'کلید API پیدا نشد.', 404);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listKeys(int $resellerId): array
    {
        return $this->db->fetchAll(
            "SELECT id, label, key_prefix, ip_allowlist, rate_limit, last_used_at, revoked_at, created_at
             FROM {$this->tables->apiKeys()}
             WHERE reseller_id = ?
             ORDER BY id DESC",
            [$resellerId]
        );
    }

    public function authenticate(string $token, string $ip, ?int $now = null): AuthContext
    {
        $token = trim($token);
        if (!str_starts_with($token, 'hm_live_') || strlen($token) < 24) {
            throw new ApiException('invalid_api_key', 'کلید API معتبر نیست.', 401);
        }
        $rows = $this->db->fetchAll(
            "SELECT * FROM {$this->tables->apiKeys()} WHERE key_prefix = ? AND revoked_at IS NULL",
            [substr($token, 0, 16)]
        );
        $hash = hash('sha256', $token);
        $key = null;
        foreach ($rows as $row) {
            if (hash_equals((string) $row['key_hash'], $hash)) {
                $key = $row;
                break;
            }
        }
        if ($key === null) {
            throw new ApiException('invalid_api_key', 'کلید API معتبر نیست.', 401);
        }
        $allow = json_decode((string) ($key['ip_allowlist'] ?? '[]'), true);
        if (!is_array($allow)) {
            throw new ApiException('ip_not_allowed', 'فهرست IP این کلید خراب است.', 403);
        }
        /** @var list<string> $allow */
        if (!$this->ipAllowed($ip, $allow)) {
            throw new ApiException('ip_not_allowed', 'این IP مجاز به استفاده از کلید نیست.', 403);
        }
        $now ??= time();
        $hits = $this->bump((int) $key['id'], intdiv($now, 60) * 60);
        $limit = max(1, (int) $key['rate_limit']);
        if ($hits > $limit) {
            throw new ApiException('rate_limited', 'سقف درخواست این کلید در این دقیقه پر شده است.', 429);
        }
        $reseller = $this->db->fetchOne(
            "SELECT * FROM {$this->tables->resellers()} WHERE id = ?",
            [(int) $key['reseller_id']]
        );
        if ($reseller === null) {
            throw new ApiException('invalid_api_key', 'کلید API معتبر نیست.', 401);
        }
        if ($reseller['status'] === 'suspended') {
            throw new ApiException('reseller_suspended', 'حساب همکاری موقتاً مسدود است.', 403);
        }
        if ($reseller['status'] !== 'approved') {
            throw new ApiException('reseller_not_approved', 'حساب همکاری هنوز تأیید نشده است.', 403);
        }
        $this->db->execute(
            "UPDATE {$this->tables->apiKeys()} SET last_used_at = ? WHERE id = ?",
            [Clock::now(), (int) $key['id']]
        );
        return new AuthContext((int) $reseller['id'], (int) $key['id'], $limit, $reseller);
    }

    public function remaining(int $keyId, int $limit, ?int $now = null): int
    {
        $now ??= time();
        $window = intdiv($now, 60) * 60;
        $row = $this->db->fetchOne(
            "SELECT hits FROM {$this->tables->rateBuckets()} WHERE key_id = ? AND window_start = ?",
            [$keyId, $window]
        );
        $hits = (int) ($row['hits'] ?? 0);
        return max(0, $limit - $hits);
    }

    /**
     * @param list<string> $rules
     */
    public function ipAllowed(string $ip, array $rules): bool
    {
        if ($rules === []) {
            return true;
        }
        foreach ($rules as $rule) {
            if (is_string($rule) && $this->matchIp($ip, $rule)) {
                return true;
            }
        }
        return false;
    }

    private function bump(int $keyId, int $window): int
    {
        $table = $this->tables->rateBuckets();
        if ($this->db->driver() === 'sqlite') {
            $row = $this->db->fetchOne(
                "INSERT INTO {$table} (key_id, window_start, hits) VALUES (?, ?, 1)
                 ON CONFLICT(key_id, window_start) DO UPDATE SET hits = hits + 1
                 RETURNING hits",
                [$keyId, $window]
            );
            return (int) ($row['hits'] ?? 0);
        }
        $this->db->execute(
            "INSERT INTO {$table} (key_id, window_start, hits) VALUES (?, ?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1)",
            [$keyId, $window]
        );
        $row = $this->db->fetchOne('SELECT LAST_INSERT_ID() AS hits');
        return (int) ($row['hits'] ?? 0);
    }

    private function matchIp(string $ip, string $rule): bool
    {
        $rule = trim($rule);
        if ($rule === '') {
            return false;
        }
        if (!str_contains($rule, '/')) {
            return $rule === $ip;
        }
        [$net, $bitsRaw] = explode('/', $rule, 2);
        if ($bitsRaw === '' || !ctype_digit($bitsRaw)) {
            return false;
        }
        $bits = (int) $bitsRaw;
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($net);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }
        $total = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $total) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }
}
