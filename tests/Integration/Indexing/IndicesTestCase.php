<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Indexing;

use Alnv\ProSearchIndexerContaoAdapterBundle\Chunking\TextChunker;
use Alnv\ProSearchIndexerContaoAdapterBundle\Chunking\UnicodeTokenizer;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndexDocument;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Migration\IndicesChunksMigration;
use Alnv\ProSearchIndexerContaoAdapterBundle\Migration\IndicesUniqueUrlMigration;
use Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\DatabaseTestCase;

/**
 * tl_indices in the 3.x structure (as created by Contao from the old DCA) plus the 4.0 migration.
 */
abstract class IndicesTestCase extends DatabaseTestCase
{
    protected const LEGACY_TABLE_SQL = "CREATE TABLE tl_indices (id INT UNSIGNED AUTO_INCREMENT NOT NULL, tstamp INT UNSIGNED DEFAULT 0, last_indexed INT UNSIGNED DEFAULT 0, pageId INT UNSIGNED DEFAULT NULL, types BLOB DEFAULT NULL, title TEXT DEFAULT NULL, description TEXT DEFAULT NULL, url TEXT DEFAULT NULL, domain VARCHAR(255) DEFAULT '' NOT NULL, images BLOB DEFAULT NULL, document LONGBLOB DEFAULT NULL, state VARCHAR(16) DEFAULT 'active' NOT NULL, language VARCHAR(12) DEFAULT '' NOT NULL, doc_type VARCHAR(32) DEFAULT 'page' NOT NULL, origin_url TEXT DEFAULT NULL, settings BLOB DEFAULT NULL, protected CHAR(1) DEFAULT '' NOT NULL, `groups` BLOB DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB";

    protected IndicesRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement(self::LEGACY_TABLE_SQL);
        $this->connection->executeStatement('CREATE TABLE tl_page (id INT UNSIGNED NOT NULL, pid INT UNSIGNED DEFAULT 0 NOT NULL, type VARCHAR(64) DEFAULT \'\' NOT NULL, PRIMARY KEY (id))');
        $this->connection->executeStatement("INSERT INTO tl_page (id, pid, type) VALUES (1, 0, 'root'), (2, 1, 'regular'), (3, 2, 'regular'), (10, 0, 'root'), (11, 10, 'regular')");
        $this->repository = new IndicesRepository($this->connection, new TextChunker(new UnicodeTokenizer(), 600, 60));
    }

    protected function migrate(): void
    {
        (new IndicesChunksMigration($this->connection, $this->repository))->run();
        (new IndicesUniqueUrlMigration($this->connection))->run();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function document(string $url = 'https://x.test/kita', ?string $text = 'Kitas im Landkreis', array $overrides = []): IndexDocument
    {
        $values = [
            'url' => $url, 'domain' => 'x.test', 'docType' => IndexDocument::TYPE_PAGE, 'language' => 'de', 'pageId' => 2, 'rootId' => 1,
            'title' => 'Kita', 'description' => 'Teaser', 'images' => ['uuid-1'], 'types' => ['news'], 'text' => $text,
            'strong' => ['wichtig'], 'headings' => ['h1' => ['Kitas']],
        ];

        return new IndexDocument(...array_replace($values, $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(int $id): array
    {
        return $this->connection->fetchAssociative('SELECT * FROM tl_indices WHERE id=?', [$id]) ?: [];
    }

    protected function chunkCount(int $id): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices_chunks WHERE pid=?', [$id]);
    }

    protected function words(int $count, string $prefix = 'w'): string
    {
        return implode(' ', array_map(static fn (int $i): string => $prefix.$i, range(1, $count)));
    }
}
