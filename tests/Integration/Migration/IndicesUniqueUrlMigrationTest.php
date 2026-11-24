<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Migration;

use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SaveResult;
use Alnv\ProSearchIndexerContaoAdapterBundle\Migration\IndicesChunksMigration;
use Alnv\ProSearchIndexerContaoAdapterBundle\Migration\IndicesUniqueUrlMigration;
use Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Indexing\IndicesTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class IndicesUniqueUrlMigrationTest extends IndicesTestCase
{
    public function testWaitsForTheChunkMigration(): void
    {
        $this->assertFalse((new IndicesUniqueUrlMigration($this->connection))->shouldRun());
    }

    public function testDuplicatesAreResolvedAndTheKeyIsUnique(): void
    {
        $old = $this->legacy('https://x.test/a', 'delete', 100);
        $active = $this->legacy('https://x.test/a', 'active', 50);
        $newer = $this->legacy('https://x.test/a', 'delete', 200);
        $other = $this->legacy('https://x.test/b', 'active', 10);
        $noUrl1 = $this->legacy('', 'active', 10);
        $noUrl2 = $this->legacy('', 'active', 10);
        (new IndicesChunksMigration($this->connection, $this->repository))->run();

        $migration = new IndicesUniqueUrlMigration($this->connection);
        $this->assertTrue($migration->shouldRun());
        $result = $migration->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('2 duplicate', $result->getMessage());
        $this->assertFalse($migration->shouldRun());

        // the active record keeps the URL, the others are deleted through the queue
        $this->assertSame(sha1('https://x.test/a'), $this->row($active)['url_hash']);
        foreach ([$old, $newer] as $id) {
            $this->assertSame('delete', $this->row($id)['state']);
            $this->assertSame(sha1('duplicate:'.$id), $this->row($id)['url_hash']);
            $this->assertSame('pending', $this->row($id)['sync_status']);
        }

        $this->assertSame(sha1('https://x.test/b'), $this->row($other)['url_hash']);
        $this->assertNotSame($this->row($noUrl1)['url_hash'], $this->row($noUrl2)['url_hash']);
        $this->assertTrue($this->connection->createSchemaManager()->listTableIndexes('tl_indices')['url_hash']->isUnique());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->executeStatement("INSERT INTO tl_indices (url, url_hash) VALUES ('https://x.test/b', ?)", [sha1('https://x.test/b')]);
    }

    public function testWithoutActiveRecordTheNewestIsKept(): void
    {
        $old = $this->legacy('https://x.test/a', 'delete', 100);
        $newer = $this->legacy('https://x.test/a', 'delete', 200);
        (new IndicesChunksMigration($this->connection, $this->repository))->run();
        (new IndicesUniqueUrlMigration($this->connection))->run();

        $this->assertSame(sha1('https://x.test/a'), $this->row($newer)['url_hash']);
        $this->assertSame(sha1('duplicate:'.$old), $this->row($old)['url_hash']);
    }

    public function testCrawlerUpdatesTheExistingRecordInsteadOfCreatingADuplicate(): void
    {
        $this->migrate();
        $first = $this->repository->save($this->document('https://x.test/a'));
        $second = $this->repository->save($this->document('https://x.test/a', 'anderer Text'));

        $this->assertSame(SaveResult::UPDATED, $second->status);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices'));
    }

    private function legacy(string $url, string $state, int $tstamp): int
    {
        $this->connection->insert('tl_indices', ['tstamp' => $tstamp, 'pageId' => 2, 'url' => $url, 'state' => $state, 'document' => serialize(['text' => ['T']])]);

        return (int) $this->connection->lastInsertId();
    }
}
