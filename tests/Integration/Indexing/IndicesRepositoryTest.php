<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Indexing;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SaveResult;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SyncStatus;

final class IndicesRepositoryTest extends IndicesTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate();
    }

    public function testNewDocumentIsStoredWithChunksAndQueued(): void
    {
        $result = $this->repository->save($this->document(text: $this->words(1500)));
        $row = $this->row($result->id);

        $this->assertSame(SaveResult::CREATED, $result->status);
        $this->assertSame(3, $result->chunkCount);
        $this->assertSame(3, $this->chunkCount($result->id));
        $this->assertSame($this->words(1500), $row['text']);
        $this->assertSame(SyncStatus::PENDING, $row['sync_status']);
        $this->assertSame(1, (int) $row['revision']);
        $this->assertSame(sha1('https://x.test/kita'), $row['url_hash']);
        $this->assertSame(['wichtig'], unserialize($row['strong']));

        $chunks = $this->connection->fetchAllAssociative('SELECT sorting, token_count FROM tl_indices_chunks WHERE pid=? ORDER BY sorting', [$result->id]);
        $this->assertSame([[0, 600], [1, 600], [2, 420]], array_map(static fn (array $c): array => [(int) $c['sorting'], (int) $c['token_count']], $chunks));
    }

    public function testUnchangedDocumentCausesNoWrite(): void
    {
        $first = $this->repository->save($this->document());
        $this->connection->executeStatement("UPDATE tl_indices SET sync_status='done' WHERE id=?", [$first->id]);

        $second = $this->repository->save($this->document());

        $this->assertSame(SaveResult::UNCHANGED, $second->status);
        $this->assertSame(1, (int) $this->row($first->id)['revision']);
        $this->assertSame(SyncStatus::DONE, $this->row($first->id)['sync_status']);
    }

    public function testChangedTextReplacesTheChunks(): void
    {
        $id = $this->repository->save($this->document(text: $this->words(1300)))->id;
        $result = $this->repository->save($this->document(text: $this->words(200, 'neu')));

        $this->assertSame(SaveResult::UPDATED, $result->status);
        $this->assertTrue($result->chunksRebuilt);
        $this->assertSame(1, $this->chunkCount($id));
        $this->assertSame($this->words(200, 'neu'), $this->connection->fetchOne('SELECT text FROM tl_indices_chunks WHERE pid=?', [$id]));
        $this->assertSame(2, (int) $this->row($id)['revision']);
    }

    public function testChangedMetadataKeepsTheChunks(): void
    {
        $id = $this->repository->save($this->document(text: $this->words(700)))->id;
        $chunkIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_indices_chunks WHERE pid=? ORDER BY id', [$id]);

        $result = $this->repository->save($this->document(text: $this->words(700), overrides: ['title' => 'Neuer Titel']));

        $this->assertSame(SaveResult::UPDATED, $result->status);
        $this->assertFalse($result->chunksRebuilt);
        $this->assertSame($chunkIds, $this->connection->fetchFirstColumn('SELECT id FROM tl_indices_chunks WHERE pid=? ORDER BY id', [$id]));
        $this->assertSame('Neuer Titel', $this->row($id)['title']);
    }

    public function testUnchangedFileTextIsKept(): void
    {
        $id = $this->repository->save($this->document('https://x.test/files/a.pdf', 'PDF Text', ['docType' => 'file', 'sourceHash' => 'h1']))->id;

        // text = null: file unchanged, only metadata are compared
        $result = $this->repository->save($this->document('https://x.test/files/a.pdf', null, ['docType' => 'file', 'sourceHash' => 'h1', 'title' => 'Neu']));

        $this->assertSame(SaveResult::UPDATED, $result->status);
        $this->assertSame('PDF Text', $this->row($id)['text']);
        $this->assertSame(1, $this->chunkCount($id));
        $this->assertSame(['id' => $id, 'source_hash' => 'h1', 'content_hash' => hash('sha256', 'PDF Text'), 'state' => 'active'], $this->repository->findSourceState('https://x.test/files/a.pdf'));
    }

    public function testEmptyDocumentHasNoChunks(): void
    {
        $result = $this->repository->save($this->document(text: ''));

        $this->assertSame(SaveResult::CREATED, $result->status);
        $this->assertSame(0, $this->chunkCount($result->id));
        $this->assertSame('', $this->row($result->id)['text']);
    }

    public function testPreventIndexAndPreventIndexMetadata(): void
    {
        $id = $this->repository->save($this->document())->id;
        $this->connection->executeStatement('UPDATE tl_indices SET settings=? WHERE id=?', [serialize(['preventIndex']), $id]);

        $this->assertSame(SaveResult::PREVENTED, $this->repository->save($this->document(text: 'anders'))->status);
        $this->assertSame('Kitas im Landkreis', $this->row($id)['text']);

        $this->connection->executeStatement("UPDATE tl_indices SET settings=?, title='Manuell' WHERE id=?", [serialize(['preventIndexMetadata']), $id]);
        $this->repository->save($this->document(text: 'anders', overrides: ['title' => 'Automatisch']));

        $this->assertSame('Manuell', $this->row($id)['title']);
        $this->assertSame('anders', $this->row($id)['text']);
    }

    public function testDeletionRemovesChunksAndQueuesTheRecord(): void
    {
        $id = $this->repository->save($this->document(text: $this->words(1000)))->id;
        $this->connection->executeStatement("UPDATE tl_indices SET sync_status='done' WHERE id=?", [$id]);

        $this->assertTrue($this->repository->markDeletedByUrl('https://x.test/kita'));

        $row = $this->row($id);
        $this->assertSame(States::DELETE, $row['state']);
        $this->assertSame(SyncStatus::PENDING, $row['sync_status']);
        $this->assertSame(0, $this->chunkCount($id));
        $this->assertFalse($this->repository->markDeletedByUrl('https://x.test/unknown'));
    }

    public function testReactivatedDocumentGetsNewChunks(): void
    {
        $id = $this->repository->save($this->document(text: $this->words(700)))->id;
        $this->repository->markDeletedByUrl('https://x.test/kita');

        $result = $this->repository->save($this->document(text: $this->words(700)));

        $this->assertSame(SaveResult::UPDATED, $result->status);
        $this->assertSame(States::ACTIVE, $this->row($id)['state']);
        $this->assertSame(2, $this->chunkCount($id));
    }

    public function testClearMarksAllButManuallyMaintainedRecords(): void
    {
        $a = $this->repository->save($this->document('https://x.test/a'))->id;
        $b = $this->repository->save($this->document('https://x.test/b'))->id;
        $this->connection->executeStatement('UPDATE tl_indices SET settings=? WHERE id=?', [serialize(['preventIndex']), $b]);

        $this->assertSame(1, $this->repository->markAllDeleted());
        $this->assertSame(States::DELETE, $this->row($a)['state']);
        $this->assertSame(States::ACTIVE, $this->row($b)['state']);
    }

    public function testOrphanedChunksAreRemoved(): void
    {
        $id = $this->repository->save($this->document(text: $this->words(700)))->id;
        $this->connection->executeStatement('DELETE FROM tl_indices WHERE id=?', [$id]);

        $this->assertSame(2, $this->repository->deleteOrphanedChunks());
        $this->assertSame(0, $this->chunkCount($id));
    }

    public function testVeryLargeDocument(): void
    {
        $text = str_repeat('Die Kreisverwaltung informiert über Kitas, Schulen und den Bürgerservice. ', 12000);
        $start = microtime(true);
        $result = $this->repository->save($this->document(text: $text));

        $this->assertGreaterThan(300, $result->chunkCount);
        $this->assertSame($result->chunkCount, $this->chunkCount($result->id));
        $this->assertLessThan(10, microtime(true) - $start);
    }
}
