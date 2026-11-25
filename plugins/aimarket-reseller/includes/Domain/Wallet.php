<?php
/**
 * Reseller wallet. Debits are a single conditional UPDATE so concurrent
 * requests cannot drive the balance below zero.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class Wallet
{
    public function __construct(
        private readonly Database $db,
        private readonly string $wallets,
        private readonly string $ledger
    ) {
    }

    public function open(int $resellerId, string $currency): void
    {
        $existing = $this->db->fetchOne(
            "SELECT reseller_id FROM {$this->wallets} WHERE reseller_id = ?",
            [$resellerId]
        );
        if ($existing !== null) {
            return;
        }
        $this->db->insert(
            "INSERT INTO {$this->wallets} (reseller_id, balance_minor, currency, version, updated_at) VALUES (?, 0, ?, 0, ?)",
            [$resellerId, strtoupper($currency), Clock::now()]
        );
    }

    public function balance(int $resellerId): ?array
    {
        return $this->db->fetchOne(
            "SELECT reseller_id, balance_minor, currency, version, updated_at FROM {$this->wallets} WHERE reseller_id = ?",
            [$resellerId]
        );
    }

    /**
     * @return array{ledger_id: int, balance_after: int}
     */
    public function apply(
        int $resellerId,
        string $direction,
        int $amount,
        string $type,
        string $referenceType,
        string $referenceId,
        string $note,
        ?int $actorUserId
    ): array {
        if ($amount <= 0) {
            throw new ApiException('invalid_amount', 'مبلغ باید بزرگ‌تر از صفر باشد.', 422);
        }
        if ($direction !== 'credit' && $direction !== 'debit') {
            throw new \InvalidArgumentException('Unknown wallet direction.');
        }
        $own = !$this->db->inTransaction();
        if ($own) {
            $this->db->begin();
        }
        try {
            if ($this->db->driver() === 'mysql') {
                $this->db->fetchOne(
                    "SELECT balance_minor FROM {$this->wallets} WHERE reseller_id = ? FOR UPDATE",
                    [$resellerId]
                );
            }
            if ($direction === 'debit') {
                $updated = $this->db->execute(
                    "UPDATE {$this->wallets}
                     SET balance_minor = balance_minor - ?, version = version + 1, updated_at = ?
                     WHERE reseller_id = ? AND balance_minor >= ?",
                    [$amount, Clock::now(), $resellerId, $amount]
                );
                if ($updated !== 1) {
                    $exists = $this->balance($resellerId);
                    throw new ApiException(
                        $exists === null ? 'wallet_missing' : 'insufficient_funds',
                        $exists === null ? 'کیف پول این همکار ساخته نشده است.' : 'موجودی کیف پول کافی نیست.',
                        $exists === null ? 409 : 402
                    );
                }
            } else {
                $updated = $this->db->execute(
                    "UPDATE {$this->wallets}
                     SET balance_minor = balance_minor + ?, version = version + 1, updated_at = ?
                     WHERE reseller_id = ?",
                    [$amount, Clock::now(), $resellerId]
                );
                if ($updated !== 1) {
                    throw new ApiException('wallet_missing', 'کیف پول این همکار ساخته نشده است.', 409);
                }
            }
            $afterRow = $this->db->fetchOne(
                "SELECT balance_minor FROM {$this->wallets} WHERE reseller_id = ?",
                [$resellerId]
            );
            $after = (int) ($afterRow['balance_minor'] ?? 0);
            if ($after < 0) {
                throw new ApiException('insufficient_funds', 'موجودی کیف پول کافی نیست.', 402);
            }
            $ledgerId = $this->db->insert(
                "INSERT INTO {$this->ledger}
                (reseller_id, direction, entry_type, amount_minor, balance_after, reference_type, reference_id, note, actor_user_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $resellerId,
                    $direction,
                    $type,
                    $amount,
                    $after,
                    $referenceType,
                    $referenceId,
                    $note,
                    $actorUserId,
                    Clock::now(),
                ]
            );
            if ($own) {
                $this->db->commit();
            }
            return ['ledger_id' => $ledgerId, 'balance_after' => $after];
        } catch (\Throwable $e) {
            if ($own) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function transactions(int $resellerId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $totalRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM {$this->ledger} WHERE reseller_id = ?",
            [$resellerId]
        );
        $items = $this->db->fetchAll(
            "SELECT id, direction, entry_type, amount_minor, balance_after, reference_type, reference_id, note, created_at
             FROM {$this->ledger}
             WHERE reseller_id = ?
             ORDER BY id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$resellerId]
        );
        return ['items' => $items, 'total' => (int) ($totalRow['c'] ?? 0)];
    }
}
