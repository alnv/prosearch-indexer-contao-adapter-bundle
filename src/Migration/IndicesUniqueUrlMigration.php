<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Migration;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SyncStatus;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

class IndicesUniqueUrlMigration extends AbstractMigration
{
    private const string T = IndicesRepository::TABLE;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'ProSearch 4.0: unique URL in tl_indices';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::T])) {
            return false;
        }

        $columns = array_map('strtolower', array_keys($schemaManager->listTableColumns(self::T)));

        // wait until IndicesChunksMigration created and filled url_hash
        if (!\in_array('url_hash', $columns, true) || $this->connection->fetchOne("SELECT 1 FROM " . self::T . " WHERE url_hash='' AND url IS NOT NULL AND url<>'' LIMIT 1")) {
            return false;
        }

        $index = $schemaManager->listTableIndexes(self::T)['url_hash'] ?? null;

        return null === $index || !$index->isUnique();
    }

    public function run(): MigrationResult
    {
        $resolved = $this->connection->transactional(function (Connection $connection): int {
            // records without URL can never be found by URL; keep them unique, they are cleaned up by the queue
            $connection->executeStatement("UPDATE " . self::T . " SET url_hash=SHA1(CONCAT('nourl:', id)) WHERE url IS NULL OR url=''");

            $resolved = 0;
            $hashes = $connection->fetchFirstColumn('SELECT url_hash FROM ' . self::T . ' GROUP BY url_hash HAVING COUNT(*)>1');

            foreach ($hashes as $hash) {
                $ids = $connection->fetchFirstColumn(
                    'SELECT id FROM ' . self::T . ' WHERE url_hash=? ORDER BY state=? DESC, tstamp DESC, id ASC',
                    [$hash, States::ACTIVE],
                );

                foreach (\array_slice($ids, 1) as $id) {
                    $connection->executeStatement(
                        "UPDATE " . self::T . " SET url_hash=SHA1(CONCAT('duplicate:', id)), state=?, revision=revision+1, sync_status=?, sync_attempts=0, sync_next_at=0, chunk_count=0 WHERE id=?",
                        [States::DELETE, SyncStatus::PENDING, (int)$id],
                    );
                    $connection->executeStatement('DELETE FROM ' . IndicesRepository::CHUNK_TABLE . ' WHERE pid=?', [(int)$id]);
                    ++$resolved;
                }
            }

            return $resolved;
        });

        $indexes = $this->connection->createSchemaManager()->listTableIndexes(self::T);

        if (isset($indexes['url_hash'])) {
            $this->connection->executeStatement('DROP INDEX url_hash ON ' . self::T);
        }

        $this->connection->executeStatement('CREATE UNIQUE INDEX url_hash ON ' . self::T . ' (url_hash)');

        return $this->createResult(true, \sprintf('Unique key on tl_indices.url_hash created, %d duplicate records marked for deletion.', $resolved));
    }
}
