<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SyncStatus;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class IndexQueue
{
    private const string T = IndicesRepository::TABLE;

    public function __construct(
        private readonly Connection $connection,
        private readonly int        $claimTtl = 300,
        private readonly int        $maxAttempts = 5,
        private readonly int        $retryDelay = 60,
        private readonly int        $maxRetryDelay = 3600,
    )
    {
    }

    public static function newClaimToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function claim(string $token, int $limit, ?array $ids = null, ?int $now = null): array
    {
        $now ??= time();

        if (null !== $ids) {
            if ([] === $ids) {
                return [];
            }

            $this->connection->executeStatement(
                'UPDATE ' . self::T . ' SET sync_claim=?, sync_claimed_until=? WHERE id IN (?) AND sync_claimed_until<? AND sync_status<>?',
                [$token, $now + $this->claimTtl, $ids, $now, SyncStatus::DONE],
                [2 => ArrayParameterType::INTEGER],
            );
        } else {
            // single statement: atomic between concurrent workers (row locks), ordered by due date
            $this->connection->executeStatement(
                'UPDATE ' . self::T . ' SET sync_claim=?, sync_claimed_until=? WHERE sync_status=? AND sync_next_at<=? AND sync_claimed_until<? ORDER BY sync_next_at, id LIMIT ' . max(1, $limit),
                [$token, $now + $this->claimTtl, SyncStatus::PENDING, $now, $now],
            );
        }

        return $this->connection->fetchAllAssociative('SELECT * FROM ' . self::T . ' WHERE sync_claim=? ORDER BY id', [$token]);
    }

    public function confirmIndexed(int $id, int $revision, string $token, string $index, ?int $now = null): bool
    {
        $updated = $this->connection->executeStatement(
            "UPDATE " . self::T . " SET sync_status=?, synced_revision=?, synced_index=?, last_indexed=?, sync_attempts=0, sync_error='', sync_next_at=0, sync_claim='', sync_claimed_until=0 WHERE id=? AND revision=? AND sync_claim=? AND state=?",
            [SyncStatus::DONE, $revision, $index, $now ?? time(), $id, $revision, $token, States::ACTIVE],
        );

        if (0 === $updated) {
            $this->release([$id], $token);
        }

        return $updated > 0;
    }

    public function confirmDeleted(int $id, int $revision, string $token): bool
    {
        return $this->connection->transactional(function (Connection $connection) use ($id, $revision, $token): bool {
            $deleted = $connection->executeStatement(
                'DELETE FROM ' . self::T . ' WHERE id=? AND revision=? AND sync_claim=? AND state=?',
                [$id, $revision, $token, States::DELETE],
            );

            if (0 === $deleted) {
                $this->release([$id], $token);

                return false;
            }

            $connection->executeStatement('DELETE FROM ' . IndicesRepository::CHUNK_TABLE . ' WHERE pid=?', [$id]);

            return true;
        });
    }

    public function clearSyncedIndex(int $id, string $token): void
    {
        $this->connection->executeStatement("UPDATE " . self::T . " SET synced_index='' WHERE id=? AND sync_claim=?", [$id, $token]);
    }

    public function fail(int $id, string $token, string $error, ?int $now = null): bool
    {
        $now ??= time();
        $attempts = (int)$this->connection->fetchOne('SELECT sync_attempts FROM ' . self::T . ' WHERE id=? AND sync_claim=?', [$id, $token]) + 1;
        $permanent = $attempts >= $this->maxAttempts;

        $this->connection->executeStatement(
            "UPDATE " . self::T . " SET sync_status=?, sync_attempts=?, sync_error=?, sync_next_at=?, sync_claim='', sync_claimed_until=0 WHERE id=? AND sync_claim=?",
            [$permanent ? SyncStatus::FAILED : SyncStatus::PENDING, $attempts, self::limit($error), $permanent ? 0 : $now + $this->delay($attempts), $id, $token],
        );

        return $permanent;
    }

    public function postpone(array $ids, string $token, string $error, int $delay, ?int $now = null): int
    {
        if ([] === $ids) {
            return 0;
        }

        return $this->connection->executeStatement(
            "UPDATE " . self::T . " SET sync_error=?, sync_next_at=?, sync_claim='', sync_claimed_until=0 WHERE id IN (?) AND sync_claim=?",
            [self::limit($error), ($now ?? time()) + $delay, $ids, $token],
            [2 => ArrayParameterType::INTEGER],
        );
    }

    public function release(array $ids, string $token): void
    {
        if ([] === $ids) {
            return;
        }

        $this->connection->executeStatement(
            "UPDATE " . self::T . " SET sync_claim='', sync_claimed_until=0 WHERE id IN (?) AND sync_claim=?",
            [$ids, $token],
            [0 => ArrayParameterType::INTEGER],
        );
    }

    public function retryFailed(): int
    {
        return $this->connection->executeStatement(
            "UPDATE " . self::T . " SET sync_status=?, sync_attempts=0, sync_next_at=0 WHERE sync_status=?",
            [SyncStatus::PENDING, SyncStatus::FAILED],
        );
    }

    public function resetDelays(): int
    {
        return $this->connection->executeStatement('UPDATE ' . self::T . ' SET sync_next_at=0 WHERE sync_status=? AND sync_next_at>0', [SyncStatus::PENDING]);
    }

    public function statistics(?int $now = null): array
    {
        $now ??= time();
        $row = $this->connection->fetchAssociative(
            'SELECT
                SUM(sync_status=?) AS pending,
                SUM(sync_status=? AND sync_next_at<=? AND sync_claimed_until<?) AS due,
                SUM(sync_claimed_until>=?) AS processing,
                SUM(sync_status=?) AS done,
                SUM(sync_status=?) AS failed,
                SUM(state=?) AS `delete`
            FROM ' . self::T,
            [SyncStatus::PENDING, SyncStatus::PENDING, $now, $now, $now, SyncStatus::DONE, SyncStatus::FAILED, States::DELETE],
        ) ?: [];

        $stats = array_map('intval', $row + ['pending' => 0, 'due' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0, 'delete' => 0]);
        $stats['chunks'] = (int)$this->connection->fetchOne('SELECT COUNT(*) FROM ' . IndicesRepository::CHUNK_TABLE);

        return $stats;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function delay(int $attempts): int
    {
        return (int)min($this->maxRetryDelay, $this->retryDelay * 2 ** max(0, $attempts - 1));
    }

    private static function limit(string $error): string
    {
        return mb_substr(trim($error), 0, 1000);
    }
}
