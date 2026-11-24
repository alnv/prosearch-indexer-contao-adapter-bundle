<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests against a real MySQL/MariaDB server in a throw-away database.
 *
 * Skipped unless PROSEARCH_STATS_TEST_DATABASE_URL (mysql://user:pass@host:port/any) is set. The database
 * "ps_stats_test_<random>" is created per test and dropped afterwards; no other database is touched.
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * tl_search_stats_log exactly as created by Contao from the DCA ("contao:migrate --dry-run").
     */
    protected const LOG_TABLE_SQL = "CREATE TABLE tl_search_stats_log (id INT UNSIGNED AUTO_INCREMENT NOT NULL, tstamp INT UNSIGNED DEFAULT 0 NOT NULL, keyword VARCHAR(255) DEFAULT '' NOT NULL, hits INT UNSIGNED DEFAULT 0 NOT NULL, clicks INT UNSIGNED DEFAULT 0 NOT NULL, urls TEXT DEFAULT NULL, types VARCHAR(255) DEFAULT '' NOT NULL, source VARCHAR(1024) DEFAULT '' NOT NULL, legacy CHAR(1) DEFAULT '' NOT NULL, INDEX tstamp_keyword_hits_clicks_legacy (tstamp, keyword, hits, clicks, legacy), INDEX keyword_tstamp_hits_clicks (keyword, tstamp, hits, clicks), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB";

    protected Connection $connection;
    protected \DateTimeZone $tz;

    private ?Connection $server = null;
    private string $database = '';

    protected function setUp(): void
    {
        $url = (string) getenv('PROSEARCH_STATS_TEST_DATABASE_URL');

        if ('' === $url) {
            $this->markTestSkipped('PROSEARCH_STATS_TEST_DATABASE_URL is not set.');
        }

        $params = (new DsnParser(['mysql' => 'pdo_mysql']))->parse($url);
        unset($params['dbname']);

        $this->database = 'ps_stats_test_'.bin2hex(random_bytes(4));
        $this->server = DriverManager::getConnection($params);
        $this->server->executeStatement('CREATE DATABASE `'.$this->database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->connection = DriverManager::getConnection($params + ['dbname' => $this->database, 'charset' => 'utf8mb4']);
        $this->tz = new \DateTimeZone('Europe/Berlin');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }

        if ($this->server && '' !== $this->database) {
            $this->server->executeStatement('DROP DATABASE IF EXISTS `'.$this->database.'`');
            $this->server->close();
        }
    }

    protected function at(string $local): \DateTimeImmutable
    {
        return new \DateTimeImmutable($local, $this->tz);
    }

    /**
     * @param array<string, mixed> $extra further columns
     */
    protected function insertLog(string $keyword, string $local, int $hits, int $times = 1, array $extra = []): void
    {
        for ($i = 0; $i < $times; ++$i) {
            $this->connection->insert('tl_search_stats_log', ['keyword' => $keyword, 'tstamp' => $this->at($local)->getTimestamp() + $i, 'hits' => $hits] + $extra);
        }
    }
}
