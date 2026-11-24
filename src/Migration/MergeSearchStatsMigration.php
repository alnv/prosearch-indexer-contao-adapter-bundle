<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Migration;

use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsRepository;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

class MergeSearchStatsMigration extends AbstractMigration
{
    public const OLD_TABLE = 'tl_search_stats';

    private const LOG_TABLE = SearchStatsRepository::LOG_TABLE;
    private const READ_BATCH = 500;
    private const INSERT_BATCH = 1000;
    private const COLUMNS = ['tstamp', 'keyword', 'hits', 'clicks', 'urls', 'types', 'source', 'legacy'];

    /**
     * Columns added in 4.0 (exactly as generated from the DCA).
     */
    private const NEW_COLUMNS = [
        'clicks' => 'INT UNSIGNED DEFAULT 0 NOT NULL',
        'urls' => 'TEXT DEFAULT NULL',
        'types' => "VARCHAR(255) DEFAULT '' NOT NULL",
        'source' => "VARCHAR(1024) DEFAULT '' NOT NULL",
        'legacy' => "CHAR(1) DEFAULT '' NOT NULL",
    ];

    private const INDEXES = [
        'tstamp_keyword_hits_clicks_legacy' => '(tstamp, keyword, hits, clicks, legacy)',
        'keyword_tstamp_hits_clicks' => '(keyword, tstamp, hits, clicks)',
    ];

    /**
     * Indexes of the first dashboard version, replaced by the covering indexes.
     */
    private const OBSOLETE_INDEXES = ['tstamp', 'keyword_tstamp', 'tstamp_keyword_hits_clicks'];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'ProSearch: merge tl_search_stats into tl_search_stats_log';
    }

    public function shouldRun(): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([self::OLD_TABLE]);
    }

    public function run(): MigrationResult
    {
        $this->prepareLogTable();

        // Restart after an interrupted run: the old table still exists, so all legacy rows come from that run
        $this->connection->executeStatement('DELETE FROM ' . self::LOG_TABLE . " WHERE legacy = '1'");

        $expected = ['searches' => 0, 'hits' => 0, 'clicks' => 0];
        $buffer = [];
        $lastId = 0;

        do {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, tstamp, keywords, `count`, hits, clicks, urls, types, source FROM ' . self::OLD_TABLE . ' WHERE id > ? ORDER BY id LIMIT ' . self::READ_BATCH,
                [$lastId],
            );

            foreach ($rows as $row) {
                $lastId = (int)$row['id'];

                foreach (LegacySearchStatsExpander::totals($row) as $key => $value) {
                    $expected[$key] += $value;
                }

                foreach (LegacySearchStatsExpander::expand($row) as $logRow) {
                    $buffer[] = $logRow;

                    if (\count($buffer) >= self::INSERT_BATCH) {
                        $this->insert($buffer);
                        $buffer = [];
                    }
                }
            }
        } while (\count($rows) === self::READ_BATCH);

        $this->insert($buffer);

        $actual = $this->connection->fetchAssociative('SELECT COUNT(*) AS searches, COALESCE(SUM(hits), 0) AS hits, COALESCE(SUM(clicks), 0) AS clicks FROM ' . self::LOG_TABLE . " WHERE legacy = '1'") ?: [];

        foreach ($expected as $key => $value) {
            if ($value !== (int)($actual[$key] ?? -1)) {
                // Exception instead of a failed result: aborts "contao:migrate" before the schema update
                throw new \RuntimeException(\sprintf('ProSearch: the merged statistics do not match (%s: expected %d, got %d). tl_search_stats was kept, the migration can be rerun.', $key, $value, (int)($actual[$key] ?? -1)));
            }
        }

        $this->finishIndexes();
        $this->connection->executeStatement('DROP TABLE ' . self::OLD_TABLE);

        return $this->createResult(true, \sprintf('Merged %d searches (%d hits, %d clicks) from tl_search_stats into tl_search_stats_log and dropped tl_search_stats.', $expected['searches'], $expected['hits'], $expected['clicks']));
    }

    private function prepareLogTable(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::LOG_TABLE])) {
            // Indexes are created after the data (finishIndexes), that is considerably faster for large tables
            $this->connection->executeStatement(
                'CREATE TABLE ' . self::LOG_TABLE . " (id INT UNSIGNED AUTO_INCREMENT NOT NULL, tstamp INT UNSIGNED DEFAULT 0 NOT NULL, keyword VARCHAR(255) DEFAULT '' NOT NULL, hits INT UNSIGNED DEFAULT 0 NOT NULL, "
                . implode(', ', array_map(static fn(string $name, string $definition): string => $name . ' ' . $definition, array_keys(self::NEW_COLUMNS), self::NEW_COLUMNS))
                . ', PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            );

            return;
        }

        $columns = array_change_key_case($schemaManager->listTableColumns(self::LOG_TABLE));
        $missing = array_diff_key(self::NEW_COLUMNS, $columns);

        if ([] !== $missing) {
            $this->connection->executeStatement('ALTER TABLE ' . self::LOG_TABLE . ' ' . implode(', ', array_map(static fn(string $name, string $definition): string => 'ADD ' . $name . ' ' . $definition, array_keys($missing), $missing)));
        }
    }

    private function finishIndexes(): void
    {
        $indexes = array_change_key_case($this->connection->createSchemaManager()->listTableIndexes(self::LOG_TABLE));

        foreach (self::OBSOLETE_INDEXES as $name) {
            if (isset($indexes[$name])) {
                $this->connection->executeStatement('DROP INDEX ' . $name . ' ON ' . self::LOG_TABLE);
            }
        }

        foreach (self::INDEXES as $name => $columns) {
            if (!isset($indexes[$name])) {
                $this->connection->executeStatement('CREATE INDEX ' . $name . ' ON ' . self::LOG_TABLE . ' ' . $columns);
            }
        }
    }

    /**
     * @param list<array<string, int|string|null>> $rows
     */
    private function insert(array $rows): void
    {
        if ([] === $rows) {
            return;
        }

        $placeholders = '(' . implode(', ', array_fill(0, \count(self::COLUMNS), '?')) . ')';
        $params = [];

        foreach ($rows as $row) {
            foreach (self::COLUMNS as $column) {
                $params[] = $row[$column];
            }
        }

        $this->connection->executeStatement(
            'INSERT INTO ' . self::LOG_TABLE . ' (' . implode(', ', self::COLUMNS) . ') VALUES ' . implode(', ', array_fill(0, \count($rows), $placeholders)),
            $params,
        );
    }
}
