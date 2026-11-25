<?php
/**
 * One row per API request. Bodies, tokens, and credentials are never stored.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class RequestLog
{
    public function __construct(private readonly Database $db, private readonly Tables $tables)
    {
    }

    public function write(
        string $requestId,
        ?int $resellerId,
        ?int $keyId,
        string $method,
        string $route,
        int $status,
        string $ip,
        int $durationMs,
        string $errorCode
    ): void {
        $this->db->insert(
            "INSERT INTO {$this->tables->apiLogs()}
            (request_id, reseller_id, key_id, method, route, status_code, ip, duration_ms, error_code, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $requestId,
                $resellerId,
                $keyId,
                substr($method, 0, 10),
                substr($route, 0, 190),
                $status,
                substr($ip, 0, 45),
                max(0, $durationMs),
                substr($errorCode, 0, 60),
                Clock::now(),
            ]
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function recent(int $page, int $perPage, string $errorCode = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $where = '1 = 1';
        $params = [];
        if ($errorCode !== '') {
            $where .= ' AND error_code = ?';
            $params[] = $errorCode;
        }
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM {$this->tables->apiLogs()} WHERE {$where}",
            $params
        );
        $items = $this->db->fetchAll(
            "SELECT * FROM {$this->tables->apiLogs()} WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => $items, 'total' => (int) ($total['c'] ?? 0)];
    }
}
