<?php
/**
 * Small database port shared by WordPress ($wpdb) and the test PDO driver.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

interface Database
{
    public function driver(): string;

    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    public function inTransaction(): bool;

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array;

    /**
     * @param list<mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array;

    /**
     * @param list<mixed> $params
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * @param list<mixed> $params
     */
    public function insert(string $sql, array $params = []): int;

    public function exec(string $sql): void;

    public function tableExists(string $table): bool;

    public function isUniqueViolation(\Throwable $e): bool;
}
