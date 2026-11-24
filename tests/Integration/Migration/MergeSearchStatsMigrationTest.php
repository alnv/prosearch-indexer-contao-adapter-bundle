<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Migration;

use Alnv\ProSearchIndexerContaoAdapterBundle\Migration\MergeSearchStatsMigration;
use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsLogger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\DatabaseTestCase;

/**
 * Merging tl_search_stats into tl_search_stats_log against MySQL/MariaDB.
 */
final class MergeSearchStatsMigrationTest extends DatabaseTestCase
{
    /**
     * tl_search_stats as it existed up to 3.x.
     */
    private const OLD_TABLE_SQL = "CREATE TABLE tl_search_stats (id INT UNSIGNED AUTO_INCREMENT NOT NULL, tstamp INT UNSIGNED DEFAULT 0, hits INT UNSIGNED DEFAULT 0, `count` INT UNSIGNED DEFAULT 0, clicks INT UNSIGNED DEFAULT 0, keywords VARCHAR(255) DEFAULT '' NOT NULL, types TEXT DEFAULT NULL, urls TEXT DEFAULT NULL, source TEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB";

    /**
     * tl_search_stats_log of the first dashboard version (before the merge).
     */
    private const FIRST_LOG_TABLE_SQL = "CREATE TABLE tl_search_stats_log (id INT UNSIGNED AUTO_INCREMENT NOT NULL, tstamp INT UNSIGNED DEFAULT 0 NOT NULL, hits INT UNSIGNED DEFAULT 0 NOT NULL, keyword VARCHAR(255) DEFAULT '' NOT NULL, INDEX tstamp (tstamp), INDEX keyword_tstamp (keyword, tstamp), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB";

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement(self::OLD_TABLE_SQL);
        $this->seedOldTable();
    }

    public function testMergesIntoTheExistingLogAndDropsTheOldTable(): void
    {
        $this->connection->executeStatement(self::FIRST_LOG_TABLE_SQL);
        $this->connection->insert('tl_search_stats_log', ['keyword' => 'schon protokolliert', 'tstamp' => $this->at('2026-11-23 10:00')->getTimestamp(), 'hits' => 4]);

        $migration = new MergeSearchStatsMigration($this->connection);
        $this->assertTrue($migration->shouldRun());

        $result = $migration->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('Merged 16 searches (37 hits, 3 clicks) from tl_search_stats into tl_search_stats_log and dropped tl_search_stats.', $result->getMessage());
        $this->assertFalse($this->connection->createSchemaManager()->tablesExist(['tl_search_stats']));
        $this->assertFalse($migration->shouldRun(), 'runs only once');

        $this->assertMergedRows();
        $this->assertSame(
            ['keyword' => 'schon protokolliert', 'hits' => '4', 'legacy' => ''],
            array_map('strval', $this->connection->fetchAssociative("SELECT keyword, hits, legacy FROM tl_search_stats_log WHERE legacy = ''")),
            'existing log rows are kept unchanged',
        );
        $this->assertFinalSchema();
    }

    public function testCreatesTheLogTableIfItDoesNotExist(): void
    {
        (new MergeSearchStatsMigration($this->connection))->run();

        $this->assertMergedRows();
        $this->assertFinalSchema();
    }

    public function testRerunAfterAnInterruptedRunDoesNotDuplicateRows(): void
    {
        $this->connection->executeStatement(self::LOG_TABLE_SQL);
        // Rows of an interrupted run: the old table still exists
        $this->insertLog('kfz', '2026-11-20 12:00', 9, 7, ['legacy' => '1']);

        (new MergeSearchStatsMigration($this->connection))->run();

        $this->assertMergedRows();
    }

    private function seedOldTable(): void
    {
        $rows = [
            // 5 searches with 13 hits in total, 2 categories, 2 sources (3 + 1 searches), 2 clicks
            ['keywords' => 'Kfz ', 'count' => 5, 'hits' => 13, 'clicks' => 2, 'tstamp' => $this->at('2026-11-20 12:00')->getTimestamp(),
                'types' => serialize(['pdf', 'page']), 'urls' => serialize(['https://example.org/a', 'https://example.org/b']),
                'source' => serialize([['source' => 'https://example.org/de/suche?x=1', 'click' => 3], ['source' => 'https://example.org/de/', 'click' => 1]])],
            // same term with other categories → second row of the old table
            ['keywords' => 'kfz', 'count' => 1, 'hits' => 0, 'clicks' => 1, 'tstamp' => $this->at('2026-11-21 08:00')->getTimestamp(),
                'types' => serialize([]), 'urls' => serialize([]), 'source' => null],
            ['keywords' => 'termin', 'count' => 10, 'hits' => 24, 'clicks' => 0, 'tstamp' => $this->at('2025-03-13 14:57')->getTimestamp(),
                'types' => 'a:0:{}', 'urls' => '', 'source' => 'kaputt'],
            // not migrated: empty term, no searches
            ['keywords' => '  ', 'count' => 7, 'hits' => 3, 'clicks' => 0, 'tstamp' => 1],
            ['keywords' => 'nie gesucht', 'count' => 0, 'hits' => 0, 'clicks' => 0, 'tstamp' => 1],
        ];

        foreach ($rows as $row) {
            $this->connection->insert('tl_search_stats', $row);
        }
    }

    private function assertMergedRows(): void
    {
        $this->assertSame(
            ['searches' => 16, 'hits' => 37, 'clicks' => 3],
            array_map('intval', $this->connection->fetchAssociative("SELECT COUNT(*) AS searches, SUM(hits) AS hits, SUM(clicks) AS clicks FROM tl_search_stats_log WHERE legacy = '1'")),
        );

        $kfz = $this->connection->fetchAllAssociative("SELECT tstamp, keyword, hits, clicks, urls, types, source FROM tl_search_stats_log WHERE legacy = '1' AND tstamp = ? ORDER BY id", [$this->at('2026-11-20 12:00')->getTimestamp()]);

        $this->assertCount(5, $kfz);
        $this->assertSame(['Kfz'], array_values(array_unique(array_column($kfz, 'keyword'))), 'term is trimmed');
        $this->assertSame([3, 3, 3, 2, 2], array_map('intval', array_column($kfz, 'hits')), 'hit sum 13 is kept');
        $this->assertSame([2, 0, 0, 0, 0], array_map('intval', array_column($kfz, 'clicks')));
        $this->assertSame(['https://example.org/a', 'https://example.org/b'], SearchStatsLogger::unserializeUrls($kfz[0]['urls']));
        $this->assertNull($kfz[1]['urls']);
        $this->assertSame(['page,pdf'], array_values(array_unique(array_column($kfz, 'types'))));
        $this->assertSame(['https://example.org/de/suche', 'https://example.org/de/suche', 'https://example.org/de/suche', 'https://example.org/de/', ''], array_column($kfz, 'source'));

        $this->assertSame(10, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM tl_search_stats_log WHERE keyword = 'termin' AND source = '' AND types = ''"));
        $this->assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM tl_search_stats_log WHERE keyword IN ('', 'nie gesucht')"));
    }

    private function assertFinalSchema(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $this->assertSame(['clicks', 'hits', 'id', 'keyword', 'legacy', 'source', 'tstamp', 'types', 'urls'], $this->sorted(array_keys($schemaManager->listTableColumns('tl_search_stats_log'))));
        $this->assertSame(['keyword_tstamp_hits_clicks', 'primary', 'tstamp_keyword_hits_clicks_legacy'], $this->sorted(array_keys($schemaManager->listTableIndexes('tl_search_stats_log'))));
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        $values = array_map('strtolower', $values);
        sort($values);

        return $values;
    }
}
