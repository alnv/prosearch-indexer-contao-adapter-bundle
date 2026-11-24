<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing;

use Alnv\ProSearchIndexerContaoAdapterBundle\Chunking\Chunk;
use Alnv\ProSearchIndexerContaoAdapterBundle\Chunking\TextChunker;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class IndicesRepository
{
    public const string TABLE = 'tl_indices';
    public const string CHUNK_TABLE = 'tl_indices_chunks';

    /**
     * Rows per multi-row INSERT of chunks.
     */
    private const int CHUNK_INSERT_BATCH = 50;

    public function __construct(
        private readonly Connection  $connection,
        private readonly TextChunker $chunker,
    )
    {
    }

    public function getChunker(): TextChunker
    {
        return $this->chunker;
    }

    /**
     * Stores a captured document if it is new or changed.
     *
     * Honours the record settings: "preventIndex" (record is maintained manually) skips the update,
     * "preventIndexMetadata" keeps title, description and images.
     */
    public function save(IndexDocument $document): SaveResult
    {
        $urlHash = IndexDocument::urlHash($document->url);
        $contentHash = null !== $document->text ? hash('sha256', $document->text) : null;

        try {
            return $this->saveDocument($document, $urlHash, $contentHash);
        } catch (UniqueConstraintViolationException) {
            // another process inserted the same URL in the meantime: store as update of that record
            return $this->saveDocument($document, $urlHash, $contentHash);
        }
    }

    private function saveDocument(IndexDocument $document, string $urlHash, ?string $contentHash): SaveResult
    {
        $chunks = null;

        return $this->connection->transactional(function (Connection $connection) use ($document, $urlHash, &$chunks, &$contentHash): SaveResult {
            $row = $connection->fetchAssociative(
                'SELECT id, settings, state, checksum, content_hash, title, description, images, chunk_count FROM ' . self::TABLE . ' WHERE url_hash=? ORDER BY id LIMIT 1 FOR UPDATE',
                [$urlHash],
            );

            $settings = $row ? self::deserialize($row['settings']) : [];

            if (\in_array('preventIndex', $settings, true)) {
                return new SaveResult(SaveResult::PREVENTED, (int)$row['id']);
            }

            $metadata = $document->metadataForChecksum();

            if ($row && \in_array('preventIndexMetadata', $settings, true)) {
                $metadata['title'] = (string)$row['title'];
                $metadata['description'] = (string)$row['description'];
                $metadata['images'] = self::deserialize($row['images']);
            }

            // text unchanged (null): keep the stored text and chunks
            $contentHash ??= $row ? (string)$row['content_hash'] : hash('sha256', '');
            $checksum = IndexDocument::checksum($metadata, $contentHash);

            if ($row && States::ACTIVE === $row['state'] && $checksum === $row['checksum']) {
                return new SaveResult(SaveResult::UNCHANGED, (int)$row['id'], (int)$row['chunk_count']);
            }

            $textChanged = null !== $document->text && (!$row || $contentHash !== $row['content_hash'] || (0 === (int)$row['chunk_count'] && '' !== trim($document->text)));

            if ($textChanged) {
                $chunks = iterator_to_array($this->chunker->chunk($document->text), false);
            }

            $data = [
                'tstamp' => time(),
                'url' => $document->url,
                'url_hash' => $urlHash,
                'domain' => $document->domain,
                'doc_type' => $document->docType,
                'language' => $document->language,
                'pageId' => $document->pageId,
                'rootId' => $document->rootId,
                'title' => $metadata['title'],
                'description' => $metadata['description'],
                'images' => serialize(array_values($metadata['images'])),
                'types' => serialize(array_values($document->types)),
                'strong' => serialize(array_values($document->strong)),
                'headings' => serialize($document->headings + IndexDocument::emptyHeadings()),
                'origin_url' => $document->originUrl,
                'protected' => $document->protected ? '1' : '',
                'groups' => serialize(array_values($document->groups)),
                'state' => States::ACTIVE,
                'checksum' => $checksum,
                'content_hash' => $contentHash,
                'source_hash' => $document->sourceHash,
                'sync_status' => SyncStatus::PENDING,
                'sync_attempts' => 0,
                'sync_error' => '',
                'sync_next_at' => 0,
            ];

            if (null !== $document->text) {
                $data['text'] = $document->text;
            }

            if (null !== $chunks) {
                $data['chunk_count'] = \count($chunks);
            }

            if ($row) {
                $id = (int)$row['id'];
                $this->update($connection, $id, $data);
            } else {
                $id = $this->insert($connection, $data + ['revision' => 1, 'settings' => serialize([])]);
            }

            if (null !== $chunks) {
                $this->replaceChunks($connection, $id, $chunks);
            }

            return new SaveResult($row ? SaveResult::UPDATED : SaveResult::CREATED, $id, null !== $chunks ? \count($chunks) : (int)($row['chunk_count'] ?? 0), null !== $chunks);
        });
    }

    /**
     * Source state of a file document (to skip parsing an unchanged PDF).
     *
     * @return array{id: int, source_hash: string, content_hash: string, state: string}|null
     */
    public function findSourceState(string $url): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, source_hash, content_hash, state FROM ' . self::TABLE . ' WHERE url_hash=? AND url=? ORDER BY id LIMIT 1',
            [IndexDocument::urlHash($url), $url],
        );

        if (!$row) {
            return null;
        }

        return ['id' => (int)$row['id'], 'source_hash' => (string)$row['source_hash'], 'content_hash' => (string)$row['content_hash'], 'state' => (string)$row['state']];
    }

    /**
     * Marks a document for deletion (the queue removes it from the search index and then locally).
     * Chunks are removed immediately, they are not needed for the deletion.
     *
     * @return bool false if not found or protected by "preventIndex"/"preventIndexMetadata"
     */
    public function markDeletedByUrl(string $url): bool
    {
        $row = $this->connection->fetchAssociative('SELECT id, settings FROM ' . self::TABLE . ' WHERE url_hash=? AND url=? ORDER BY id LIMIT 1', [IndexDocument::urlHash($url), $url]);

        if (!$row || array_intersect(['preventIndex', 'preventIndexMetadata'], self::deserialize($row['settings']))) {
            return false;
        }

        $this->markDeleted([(int)$row['id']]);

        return true;
    }

    /**
     * @param list<int> $ids
     */
    public function markDeleted(array $ids): int
    {
        if ([] === $ids) {
            return 0;
        }

        return $this->connection->transactional(function (Connection $connection) use ($ids): int {
            $count = $connection->executeStatement(
                'UPDATE ' . self::TABLE . ' SET state=?, revision=revision+1, sync_status=?, sync_attempts=0, sync_error=?, sync_next_at=0, chunk_count=0, tstamp=? WHERE id IN (?)',
                [States::DELETE, SyncStatus::PENDING, '', time(), $ids],
                [4 => ArrayParameterType::INTEGER],
            );

            $connection->executeStatement('DELETE FROM ' . self::CHUNK_TABLE . ' WHERE pid IN (?)', [$ids], [ArrayParameterType::INTEGER]);

            return $count;
        });
    }

    /**
     * Marks all documents for deletion except manually maintained ones (Contao "clear search index").
     */
    public function markAllDeleted(): int
    {
        $ids = [];

        foreach ($this->connection->iterateAssociative('SELECT id, settings FROM ' . self::TABLE . ' WHERE state<>?', [States::DELETE]) as $row) {
            if (!array_intersect(['preventIndex', 'preventIndexMetadata'], self::deserialize($row['settings']))) {
                $ids[] = (int)$row['id'];
            }
        }

        $count = 0;

        foreach (array_chunk($ids, 1000) as $batch) {
            $count += $this->markDeleted($batch);
        }

        return $count;
    }

    /**
     * Queues a record again (manual re-index, changed settings in the back end).
     *
     * @param list<int> $ids
     */
    public function requeue(array $ids, bool $resetTarget = false): int
    {
        if ([] === $ids) {
            return 0;
        }

        return $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET revision=revision+1, sync_status=?, sync_attempts=0, sync_error=?, sync_next_at=0' . ($resetTarget ? ", synced_index=''" : '') . ' WHERE id IN (?)',
            [SyncStatus::PENDING, '', $ids],
            [2 => ArrayParameterType::INTEGER],
        );
    }

    /**
     * Updates text, strong texts and headings edited in the back end (re-chunks the text).
     *
     * @param array<string, list<string>> $headings
     * @param list<string> $strong
     */
    public function updateContent(int $id, string $text, array $strong, array $headings): void
    {
        $chunks = iterator_to_array($this->chunker->chunk($text), false);

        $this->connection->transactional(function (Connection $connection) use ($id, $text, $strong, $headings, $chunks): void {
            $contentHash = hash('sha256', $text);
            $this->update($connection, $id, [
                'text' => $text,
                'strong' => serialize(array_values($strong)),
                'headings' => serialize($headings + IndexDocument::emptyHeadings()),
                'content_hash' => $contentHash,
                // forces a new comparison on the next crawl
                'checksum' => '',
                'chunk_count' => \count($chunks),
                'sync_status' => SyncStatus::PENDING,
                'sync_attempts' => 0,
                'sync_error' => '',
                'sync_next_at' => 0,
                'tstamp' => time(),
            ]);
            $this->replaceChunks($connection, $id, $chunks);
        });
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, list<array{text: string, position: int, token_count: int}>> chunks per document ID
     */
    public function fetchChunks(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $result = [];
        $rows = $this->connection->iterateAssociative(
            'SELECT pid, sorting, text, token_count FROM ' . self::CHUNK_TABLE . ' WHERE pid IN (?) ORDER BY pid, sorting',
            [$ids],
            [ArrayParameterType::INTEGER],
        );

        foreach ($rows as $row) {
            $result[(int)$row['pid']][] = ['text' => (string)$row['text'], 'position' => (int)$row['sorting'], 'token_count' => (int)$row['token_count']];
        }

        return $result;
    }

    /**
     * Removes chunks whose document no longer exists (e.g. deleted directly in the database).
     */
    public function deleteOrphanedChunks(): int
    {
        return $this->connection->executeStatement(
            'DELETE c FROM ' . self::CHUNK_TABLE . ' c LEFT JOIN ' . self::TABLE . ' i ON i.id=c.pid WHERE i.id IS NULL',
        );
    }

    /**
     * Replaces the chunks of a document (call inside a transaction together with the document).
     *
     * @param list<Chunk> $chunks
     */
    public function replaceChunks(Connection $connection, int $id, array $chunks): void
    {
        $connection->executeStatement('DELETE FROM ' . self::CHUNK_TABLE . ' WHERE pid=?', [$id]);
        $now = time();

        foreach (array_chunk($chunks, self::CHUNK_INSERT_BATCH) as $batch) {
            $placeholders = [];
            $values = [];

            foreach ($batch as $chunk) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
                array_push($values, $id, $now, $chunk->position, $chunk->text, $chunk->tokenCount, $chunk->startOffset, $chunk->endOffset, $chunk->getHash());
            }

            $connection->executeStatement(
                'INSERT INTO ' . self::CHUNK_TABLE . ' (pid, tstamp, sorting, text, token_count, start_offset, end_offset, hash) VALUES ' . implode(', ', $placeholders),
                $values,
            );
        }
    }

    /**
     * Connection::insert() does not quote column names ("groups" is reserved in MySQL 8).
     *
     * @param array<string, mixed> $data
     */
    private function insert(Connection $connection, array $data): int
    {
        $columns = implode(', ', array_map($connection->quoteIdentifier(...), array_keys($data)));
        $connection->executeStatement('INSERT INTO ' . self::TABLE . ' (' . $columns . ') VALUES (' . implode(', ', array_fill(0, \count($data), '?')) . ')', array_values($data));

        return (int)$connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function update(Connection $connection, int $id, array $data): void
    {
        $assignments = [];

        foreach (array_keys($data) as $column) {
            $assignments[] = $connection->quoteIdentifier($column) . '=?';
        }

        $connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $assignments) . ', revision=revision+1 WHERE id=?',
            [...array_values($data), $id],
        );
    }

    /**
     * @return list<string>
     */
    public static function deserialize(mixed $value): array
    {
        if (\is_array($value)) {
            return array_values(array_map('strval', array_filter($value, 'is_scalar')));
        }

        if (!\is_string($value) || '' === $value || !str_starts_with($value, 'a:')) {
            return [];
        }

        $data = @unserialize($value, ['allowed_classes' => false]);

        return \is_array($data) ? array_values(array_map('strval', array_filter($data, 'is_scalar'))) : [];
    }
}