<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Migration;

use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SyncStatus;
use Alnv\ProSearchIndexerContaoAdapterBundle\Migration\IndicesChunksMigration;
use Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Indexing\IndicesTestCase;

/**
 * 3.x installation (tl_indices.document) => 4.0 structure.
 */
final class IndicesChunksMigrationTest extends IndicesTestCase
{
    public function testFreshTableWithoutDataIsMigrated(): void
    {
        $migration = $this->migration();

        $this->assertTrue($migration->shouldRun());
        $this->assertTrue($migration->run()->isSuccessful());
        $this->assertFalse($migration->shouldRun());
        $this->assertTrue($this->connection->createSchemaManager()->tablesExist(['tl_indices_chunks']));
        $this->assertNotContains('document', $this->columns());
    }

    public function testExistingInstallationIsMigratedWithoutDataLoss(): void
    {
        // 1500 tokens ("w1" … "w1500" are single tokens) => 600 + 540 + 360 tokens
        $longText = implode(' ', array_map(static fn (int $i): string => 'w'.$i, range(1, 1500)));
        $page = $this->legacy(2, 'https://x.test/kita', 'page', 'active', ['text' => [$longText], 'strong' => ['fett'], 'h1' => ['Kitas'], 'h2' => ['A', 'B'], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [], 'document' => []]);
        $pdf = $this->legacy(3, 'https://x.test/files/info.pdf', 'file', 'active', ['text' => [0 => 'Infoblatt', 5 => 'info.pdf'], 'document' => ['PDF Inhalt über Kitas'], 'strong' => ['Infoblatt'], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []]);
        $deleted = $this->legacy(11, 'https://x.test/alt', 'page', 'delete', ['text' => ['Alter Text'], 'document' => []]);
        $before = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices');

        $result = $this->migration()->run();

        $this->assertTrue($result->isSuccessful(), $result->getMessage());
        $this->assertSame($before, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices'));
        $this->assertNotContains('document', $this->columns());

        // page: text, strong and headings
        $row = $this->row($page);
        $this->assertSame($longText, $row['text']);
        $this->assertSame(['fett'], unserialize($row['strong']));
        $this->assertSame(['Kitas'], unserialize($row['headings'])['h1']);
        $this->assertSame(['A', 'B'], unserialize($row['headings'])['h2']);
        $this->assertSame(3, (int) $row['chunk_count']);
        $this->assertSame(3, $this->chunkCount($page));
        $this->assertSame(1, (int) $row['rootId']);
        $this->assertSame(sha1('https://x.test/kita'), $row['url_hash']);
        $this->assertSame(SyncStatus::PENDING, $row['sync_status']);

        // PDF: text layer => text, link/meta texts => strong
        $row = $this->row($pdf);
        $this->assertSame('PDF Inhalt über Kitas', $row['text']);
        $this->assertSame(['Infoblatt', 'info.pdf'], unserialize($row['strong']));
        $this->assertSame(1, $this->chunkCount($pdf));

        // records marked for deletion are converted, but need no chunks; they are deleted by the queue
        $row = $this->row($deleted);
        $this->assertSame('Alter Text', $row['text']);
        $this->assertSame(0, $this->chunkCount($deleted));
        $this->assertSame(10, (int) $row['rootId']);
        $this->assertSame(SyncStatus::PENDING, $row['sync_status']);
    }

    public function testRepeatedMigrationChangesNothing(): void
    {
        $id = $this->legacy(2, 'https://x.test/a', 'page', 'active', ['text' => ['Text A'], 'document' => []]);
        $this->migration()->run();
        $chunkIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_indices_chunks ORDER BY id');
        $revision = (int) $this->row($id)['revision'];

        $migration = $this->migration();

        $this->assertFalse($migration->shouldRun());
        $this->assertTrue($migration->run()->isSuccessful());
        $this->assertSame($chunkIds, $this->connection->fetchFirstColumn('SELECT id FROM tl_indices_chunks ORDER BY id'));
        $this->assertSame($revision, (int) $this->row($id)['revision']);
    }

    public function testInterruptedMigrationContinues(): void
    {
        $a = $this->legacy(2, 'https://x.test/a', 'page', 'active', ['text' => ['Text A']]);
        $b = $this->legacy(2, 'https://x.test/b', 'page', 'active', ['text' => ['Text B']]);

        // columns already created by the Contao database update, chunks of $b missing
        $this->migration()->run();
        $this->connection->executeStatement('DELETE FROM tl_indices_chunks WHERE pid=?', [$b]);
        $this->connection->executeStatement('UPDATE tl_indices SET chunk_count=0 WHERE id=?', [$b]);

        $migration = $this->migration();
        $this->assertTrue($migration->shouldRun());
        $migration->run();

        $this->assertSame(1, $this->chunkCount($a));
        $this->assertSame(1, $this->chunkCount($b));
        $this->assertFalse($this->migration()->shouldRun());
    }

    public function testUnreadableDocumentKeepsTheOldColumn(): void
    {
        $good = $this->legacy(2, 'https://x.test/good', 'page', 'active', ['text' => ['Gut']]);
        $this->connection->insert('tl_indices', ['url' => 'https://x.test/broken', 'pageId' => 2, 'state' => 'active', 'document' => 'a:1:{s:4:"text";BROKEN']);
        $broken = (int) $this->connection->lastInsertId();

        $result = $this->migration()->run();

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString((string) $broken, $result->getMessage());
        $this->assertContains('document', $this->columns());
        $this->assertNull($this->row($broken)['text']);
        $this->assertSame('a:1:{s:4:"text";BROKEN', $this->row($broken)['document']);
        $this->assertSame('Gut', $this->row($good)['text']);
        $this->assertTrue($this->migration()->shouldRun());

        // after the record was fixed, the migration completes
        $this->connection->executeStatement('UPDATE tl_indices SET document=? WHERE id=?', [serialize(['text' => ['Repariert']]), $broken]);
        $this->assertTrue($this->migration()->run()->isSuccessful());
        $this->assertNotContains('document', $this->columns());
        $this->assertSame('Repariert', $this->row($broken)['text']);
    }

    public function testSchemaMatchesTheDcaDefinition(): void
    {
        $this->migration()->run();
        $columns = $this->connection->createSchemaManager()->listTableColumns('tl_indices');

        foreach (array_keys(IndicesChunksMigration::COLUMNS) as $name) {
            $this->assertArrayHasKey(strtolower($name), array_change_key_case($columns), $name);
        }

        $indexes = array_keys($this->connection->createSchemaManager()->listTableIndexes('tl_indices'));
        $this->assertContains('url_hash', $indexes);
        $this->assertContains('sync_status_sync_next_at', $indexes);
    }

    private function migration(): IndicesChunksMigration
    {
        return new IndicesChunksMigration($this->connection, $this->repository);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function legacy(int $pageId, string $url, string $docType, string $state, array $document): int
    {
        $this->connection->insert('tl_indices', [
            'tstamp' => time(), 'last_indexed' => time(), 'pageId' => $pageId, 'url' => $url, 'domain' => 'x.test', 'state' => $state,
            'doc_type' => $docType, 'language' => 'de', 'title' => 'T', 'types' => serialize([]), 'document' => serialize($document),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return list<string>
     */
    private function columns(): array
    {
        return array_map('strtolower', array_keys($this->connection->createSchemaManager()->listTableColumns('tl_indices')));
    }
}
