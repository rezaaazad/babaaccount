<?php
/**
 * $wpdb adapter. Identifiers are never bound; values always go through esc_sql.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\WordPress;

use HooshMarket\Reseller\Domain\Database;

final class WpdbDatabase implements Database
{
    private bool $transaction = false;

    public function __construct(private readonly \wpdb $wpdb)
    {
    }

    public function driver(): string
    {
        return 'mysql';
    }

    public function begin(): void
    {
        if ($this->transaction) {
            throw new \RuntimeException('Nested transactions are not supported.');
        }
        $this->must($this->wpdb->query('START TRANSACTION'), 'START TRANSACTION');
        $this->transaction = true;
    }

    public function commit(): void
    {
        if (!$this->transaction) {
            return;
        }
        $this->must($this->wpdb->query('COMMIT'), 'COMMIT');
        $this->transaction = false;
    }

    public function rollback(): void
    {
        if (!$this->transaction) {
            return;
        }
        $this->wpdb->query('ROLLBACK');
        $this->transaction = false;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $rows = $this->withErrors(function () use ($sql, $params) {
            return $this->wpdb->get_results($this->bind($sql, $params), ARRAY_A);
        });
        return is_array($rows) ? $rows : [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->withErrors(function () use ($sql, $params) {
            return $this->wpdb->get_row($this->bind($sql, $params), ARRAY_A);
        });
        return is_array($row) ? $row : null;
    }

    public function execute(string $sql, array $params = []): int
    {
        $this->withErrors(function () use ($sql, $params) {
            return $this->wpdb->query($this->bind($sql, $params));
        });
        return (int) $this->wpdb->rows_affected;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->wpdb->insert_id;
    }

    public function exec(string $sql): void
    {
        $this->withErrors(function () use ($sql) {
            return $this->wpdb->query($sql);
        });
    }

    public function tableExists(string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }
        $row = $this->fetchOne(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        return $row !== null;
    }

    public function isUniqueViolation(\Throwable $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'Duplicate') || str_contains($message, '1062');
    }

    /**
     * @param list<mixed> $params
     */
    private function bind(string $sql, array $params): string
    {
        $parts = explode('?', $sql);
        if (count($parts) !== count($params) + 1) {
            throw new \RuntimeException('SQL placeholder count does not match parameters.');
        }
        $out = '';
        foreach ($params as $i => $param) {
            $out .= $parts[$i] . $this->literal($param);
        }
        return $out . $parts[count($params)];
    }

    private function literal(mixed $param): string
    {
        if ($param === null) {
            return 'NULL';
        }
        if (is_int($param)) {
            return (string) $param;
        }
        if (is_bool($param)) {
            return $param ? '1' : '0';
        }
        if (is_float($param)) {
            if (!is_finite($param)) {
                throw new \RuntimeException('Refusing to bind a non-finite number.');
            }
            return sprintf('%.6F', $param);
        }
        return "'" . esc_sql((string) $param) . "'";
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private function withErrors(callable $fn): mixed
    {
        $suppressed = $this->wpdb->suppress_errors(true);
        $this->wpdb->last_error = '';
        try {
            $result = $fn();
        } finally {
            $this->wpdb->suppress_errors($suppressed);
        }
        if ($result === false || $this->wpdb->last_error) {
            $error = $this->wpdb->last_error ?: 'Database query failed.';
            $this->wpdb->last_error = '';
            throw new \RuntimeException($error);
        }
        return $result;
    }

    private function must(mixed $result, string $label): void
    {
        if ($result === false) {
            $error = $this->wpdb->last_error ?: $label . ' failed.';
            $this->wpdb->last_error = '';
            throw new \RuntimeException($error);
        }
    }
}
