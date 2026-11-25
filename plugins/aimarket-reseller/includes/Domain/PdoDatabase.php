<?php
/**
 * PDO database for tests and the concurrency workers. SQLite uses BEGIN IMMEDIATE
 * so two debits cannot both pass the balance check.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class PdoDatabase implements Database
{
    private bool $transaction = false;

    private function __construct(private readonly \PDO $pdo, private readonly string $driverName)
    {
    }

    public static function sqlite(string $path): self
    {
        $pdo = new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 8000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        return new self($pdo, 'sqlite');
    }

    public static function mysql(\PDO $pdo): self
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        return new self($pdo, 'mysql');
    }

    public function driver(): string
    {
        return $this->driverName;
    }

    public function begin(): void
    {
        if ($this->transaction) {
            throw new \RuntimeException('Nested transactions are not supported.');
        }
        if ($this->driverName === 'sqlite') {
            $this->pdo->exec('BEGIN IMMEDIATE');
        } else {
            $this->pdo->beginTransaction();
        }
        $this->transaction = true;
    }

    public function commit(): void
    {
        if (!$this->transaction) {
            return;
        }
        if ($this->driverName === 'sqlite') {
            $this->pdo->exec('COMMIT');
        } else {
            $this->pdo->commit();
        }
        $this->transaction = false;
    }

    public function rollback(): void
    {
        if (!$this->transaction) {
            return;
        }
        try {
            if ($this->driverName === 'sqlite') {
                $this->pdo->exec('ROLLBACK');
            } else {
                $this->pdo->rollBack();
            }
        } catch (\Throwable) {
            // SQLite aborts the transaction itself after a constraint error.
        }
        $this->transaction = false;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->run($sql, $params);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->run($sql, $params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->run($sql, $params);
        return $stmt->rowCount();
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function tableExists(string $table): bool
    {
        $this->assertIdent($table);
        if ($this->driverName === 'sqlite') {
            $row = $this->fetchOne(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table]
            );
            return $row !== null;
        }
        $row = $this->fetchOne('SHOW TABLES LIKE ?', [$table]);
        return $row !== null;
    }

    public function isUniqueViolation(\Throwable $e): bool
    {
        if (!$e instanceof \PDOException) {
            return false;
        }
        $state = (string) $e->getCode();
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $message = $e->getMessage();
        return $state === '23000'
            || $state === '23505'
            || $driverCode === 19
            || $driverCode === 1062
            || str_contains($message, 'UNIQUE')
            || str_contains($message, 'Duplicate');
    }

    /**
     * @param list<mixed> $params
     */
    private function run(string $sql, array $params): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function assertIdent(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Unsafe identifier.');
        }
    }
}
