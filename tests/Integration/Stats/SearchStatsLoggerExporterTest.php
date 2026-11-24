<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Stats;

use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsExporter;
use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsLogger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\DatabaseTestCase;

/**
 * Writing (logger), clearing and exporting the statistics against MySQL/MariaDB.
 */
final class SearchStatsLoggerExporterTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement(self::LOG_TABLE_SQL);
        date_default_timezone_set('Europe/Berlin');
    }

    public function testLogStoresNormalizedRows(): void
    {
        $logger = new SearchStatsLogger($this->connection);
        $id = $logger->log('  Kfz  ', 3, ['pdf', 'page', '', 'leistung', 'page'], 'https://example.org/de/suche?keywords=kfz', $this->at('2026-11-23 11:00')->getTimestamp());

        $this->assertSame(
            ['keyword' => 'Kfz', 'hits' => '3', 'clicks' => '0', 'urls' => null, 'types' => 'leistung,page,pdf', 'source' => 'https://example.org/de/suche', 'legacy' => ''],
            array_map(static fn ($value) => null === $value ? null : (string) $value, $this->connection->fetchAssociative('SELECT keyword, hits, clicks, urls, types, source, legacy FROM tl_search_stats_log WHERE id = ?', [$id])),
        );

        $this->assertNull($logger->log('   ', 1), 'empty terms are not logged');
        $this->assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_search_stats_log'));
    }

    public function testClickIsAddedToTheLatestSearchOfTheTerm(): void
    {
        $logger = new SearchStatsLogger($this->connection);
        $old = $logger->log('kfz', 3, [], '', $this->at('2026-11-22 11:00')->getTimestamp());
        $latest = $logger->log('kfz', 3, [], '', $this->at('2026-11-23 11:00')->getTimestamp());

        $this->assertTrue($logger->logClick('KFZ', 'https://example.org/a'));
        $this->assertTrue($logger->logClick('kfz', 'https://example.org/a'));
        $this->assertTrue($logger->logClick('kfz', 'https://example.org/b'));
        $this->assertFalse($logger->logClick('unbekannt', 'https://example.org/a'));

        $this->assertSame('0', (string) $this->connection->fetchOne('SELECT clicks FROM tl_search_stats_log WHERE id = ?', [$old]));
        $row = $this->connection->fetchAssociative('SELECT clicks, urls FROM tl_search_stats_log WHERE id = ?', [$latest]);
        $this->assertSame('3', (string) $row['clicks']);
        $this->assertSame(['https://example.org/a', 'https://example.org/b'], SearchStatsLogger::unserializeUrls($row['urls']), 'URLs are unique');
    }

    public function testPurgeAndClear(): void
    {
        $this->insertLog('uralt', '2026-09-01 10:00', 1, 2, ['legacy' => '1']);
        $this->insertLog('neu', '2026-11-23 10:00', 1);

        $this->assertSame(0, (new SearchStatsLogger($this->connection))->purge(), 'default: keep forever');
        $this->assertSame(2, (new SearchStatsLogger($this->connection, 30))->purge($this->at('2026-11-23 12:00')->getTimestamp()));
        $this->assertSame(['neu'], $this->connection->fetchFirstColumn('SELECT keyword FROM tl_search_stats_log'));

        (new SearchStatsLogger($this->connection))->clear();
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_search_stats_log'));
    }

    public function testLoggerNeverBreaksTheSearch(): void
    {
        $this->connection->executeStatement('DROP TABLE tl_search_stats_log');
        $logger = new SearchStatsLogger($this->connection);

        $this->assertNull($logger->log('kfz', 1));
        $this->assertFalse($logger->logClick('kfz', 'https://example.org'));
    }

    public function testLogExport(): void
    {
        $this->insertLog('kfz', '2026-11-23 09:00', 5, 1, ['clicks' => 2, 'urls' => serialize(['https://example.org/a', 'https://example.org/b']), 'types' => 'page,pdf', 'source' => 'https://example.org/de/suche']);
        $this->insertLog('=HYPERLINK("http://evil")', '2026-11-23 10:00', 0, 1, ['legacy' => '1']);
        $this->insertLog('alt', '2026-11-01 10:00', 1);
        $this->insertLog('', '2026-11-23 10:30', 1);

        $rows = $this->export(SearchStatsExporter::MODE_LOG, $this->at('2026-11-20 00:00')->getTimestamp(), PHP_INT_MAX);

        $this->assertSame(['Datum', 'Suchbegriff', 'Treffer', 'Klicks', 'Aufgerufene Seiten', 'Kontext', 'Quelle', 'Altdaten'], $rows[0]);
        $this->assertCount(3, $rows, 'header + 2 rows of the period (empty term and older row excluded)');
        $this->assertSame(['23.11.2026 10:00:00', "'=HYPERLINK(\"http://evil\")", '0', '0', '', '', '', 'x'], $rows[1], 'newest first, formula is neutralized');
        $this->assertSame(['23.11.2026 09:00:00', 'kfz', '5', '2', "https://example.org/a\nhttps://example.org/b", 'page,pdf', 'https://example.org/de/suche', ''], $rows[2]);
    }

    public function testTermExportIsComplete(): void
    {
        // More terms than one export batch (2000)
        $values = [];

        for ($i = 0; $i < 2500; ++$i) {
            $values[] = \sprintf("(%d, 'begriff-%04d', %d, %d)", $this->at('2026-11-23 09:00')->getTimestamp() + $i, $i, $i % 3, 0 === $i % 100 ? 1 : 0);
        }

        $this->connection->executeStatement('INSERT INTO tl_search_stats_log (tstamp, keyword, hits, clicks) VALUES '.implode(',', $values));
        $this->insertLog('begriff-0001', '2026-11-23 12:00', 4);

        $rows = $this->export(SearchStatsExporter::MODE_TERMS, null, PHP_INT_MAX);

        $this->assertSame(['Suchbegriff', 'Suchanfragen', 'Ø Treffer', 'Suchen ohne Treffer', 'Klicks', 'Erste Suche', 'Letzte Suche'], $rows[0]);
        $this->assertCount(2501, $rows);
        $this->assertSame(['begriff-0000', '1', '0,0', '1', '1', '23.11.2026 09:00:00', '23.11.2026 09:00:00'], $rows[1]);
        $this->assertSame(['begriff-0001', '2', '2,5', '0', '0', '23.11.2026 09:00:01', '23.11.2026 12:00:00'], $rows[2]);
        $this->assertSame('begriff-2499', $rows[2500][0]);
    }

    /**
     * @return list<list<string>>
     */
    private function export(string $mode, ?int $start, int $end): array
    {
        $stream = fopen('php://memory', 'w+');
        (new SearchStatsExporter(new SearchStatsRepository($this->connection)))->write($stream, $mode, $start, $end);
        rewind($stream);

        $this->assertSame("\xEF\xBB\xBF", fread($stream, 3), 'UTF-8 BOM for Excel');

        $rows = [];

        while (false !== ($row = fgetcsv($stream, null, ';', '"', ''))) {
            $rows[] = $row;
        }

        return $rows;
    }
}
