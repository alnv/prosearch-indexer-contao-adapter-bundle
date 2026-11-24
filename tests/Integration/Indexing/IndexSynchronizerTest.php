<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Indexing;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\DocumentPayloadBuilder;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexQueue;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexSynchronizer;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SyncStatus;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\EngineCapabilities;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\DocumentFactory;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexingService;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexNameResolver;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\MappingFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Queue processing against a simulated proxy (HTTP level) and a real database.
 */
final class IndexSynchronizerTest extends IndicesTestCase
{
    private const INDEX = 'contao_search_sig_1';

    private IndexQueue $queue;

    /**
     * @var list<array{method: string, path: string, body: mixed}>
     */
    private array $requests = [];

    /**
     * @var array<string, mixed> simulated proxy state
     */
    private array $proxy = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate();
        $this->queue = new IndexQueue($this->connection, 300, 3, 60, 3600);
        $this->proxy = ['engine' => 'opensearch3', 'down' => false, 'reject' => [], 'indexExists' => false, 'mapping' => [], 'onBulk' => null];
    }

    public function testSuccessfulTransferWithChunksOnOpenSearch3(): void
    {
        $id = $this->repository->save($this->document(text: $this->words(700)))->id;

        $report = $this->synchronizer()->run();
        $row = $this->row($id);

        $this->assertSame(1, $report->indexed);
        $this->assertSame(2, $report->chunksSent);
        $this->assertSame(SyncStatus::DONE, $row['sync_status']);
        $this->assertSame(1, (int) $row['synced_revision']);
        $this->assertSame(self::INDEX, $row['synced_index']);
        $this->assertGreaterThan(0, (int) $row['last_indexed']);

        // index created with nested chunk mapping and k-NN enabled
        $create = $this->request('PUT', '/api/v1/indices/'.self::INDEX);
        $this->assertSame('nested', $create['body']['mappings']['properties']['chunks']['type']);
        $this->assertTrue($create['body']['settings']['index']['knn']);

        $bulk = $this->request('POST', '/documents/_bulk')['body']['operations'];
        $this->assertSame('index', $bulk[0]['action']);
        $this->assertSame((string) $id, $bulk[0]['id']);
        $this->assertSame($this->words(700), $bulk[0]['document']['text']);
        $this->assertCount(2, $bulk[0]['document']['chunks']);
        $this->assertSame([0, 1], array_column($bulk[0]['document']['chunks'], 'position'));
        $this->assertArrayNotHasKey('document', $bulk[0]['document']);
    }

    public function testElasticsearchGetsNoChunks(): void
    {
        $this->proxy['engine'] = 'elasticsearch';
        $this->repository->save($this->document(text: $this->words(700)));

        $report = $this->synchronizer()->run();

        $this->assertSame(1, $report->indexed);
        $this->assertSame(0, $report->chunksSent);
        $this->assertArrayNotHasKey('chunks', $this->request('PUT', '/api/v1/indices/'.self::INDEX)['body']['mappings']['properties']);
        $this->assertArrayNotHasKey('chunks', $this->request('POST', '/documents/_bulk')['body']['operations'][0]['document']);
        $this->assertSame($this->words(700), $this->request('POST', '/documents/_bulk')['body']['operations'][0]['document']['text']);
    }

    public function testOlderOpenSearchGetsNoChunks(): void
    {
        $this->proxy['engine'] = 'opensearch2';
        $this->repository->save($this->document(text: $this->words(700)));

        $this->synchronizer()->run();

        $this->assertArrayNotHasKey('chunks', $this->request('POST', '/documents/_bulk')['body']['operations'][0]['document']);
    }

    public function testFailedEngineDetectionSendsNoChunks(): void
    {
        $this->proxy['engine'] = 'unreachable';
        $this->repository->save($this->document(text: $this->words(700)));

        $this->synchronizer()->run();

        $this->assertArrayNotHasKey('chunks', $this->request('POST', '/documents/_bulk')['body']['operations'][0]['document']);
    }

    public function testExistingIndexGetsTheChunkMappingAdded(): void
    {
        $this->proxy['indexExists'] = true;
        $this->proxy['mapping'] = ['properties' => ['text' => ['type' => 'text'], 'document' => ['type' => 'text']]];
        $this->repository->save($this->document(text: $this->words(10)));

        $this->synchronizer()->run();

        $update = $this->request('PUT', '/mapping');
        $this->assertSame('nested', $update['body']['properties']['chunks']['type']);
        $this->assertArrayHasKey('image', $update['body']['properties']);
        $this->assertArrayNotHasKey('text', $update['body']['properties'], 'existing fields are not touched');
        $this->assertArrayHasKey('chunks', $this->request('POST', '/documents/_bulk')['body']['operations'][0]['document']);
    }

    public function testObjectChunkMappingRequiresARebuild(): void
    {
        $this->proxy['indexExists'] = true;
        $this->proxy['mapping'] = ['properties' => ['chunks' => ['properties' => ['text' => ['type' => 'text']]]]];
        $this->repository->save($this->document(text: $this->words(10)));

        $report = $this->synchronizer()->run();

        $this->assertSame(1, $report->indexed);
        $this->assertNull($this->request('PUT', '/mapping'));
        $this->assertArrayNotHasKey('chunks', $this->request('POST', '/documents/_bulk')['body']['operations'][0]['document']);
    }

    public function testProxyUnreachablePostponesWithoutCountingAttempts(): void
    {
        $this->proxy['down'] = true;
        $id = $this->repository->save($this->document())->id;

        $report = $this->synchronizer()->run();
        $row = $this->row($id);

        $this->assertTrue($report->proxyUnavailable);
        $this->assertSame(1, $report->postponed);
        $this->assertSame(SyncStatus::PENDING, $row['sync_status']);
        $this->assertSame(0, (int) $row['sync_attempts']);
        $this->assertGreaterThan(time(), (int) $row['sync_next_at']);
        $this->assertStringContainsString('not reachable', $row['sync_error']);
        $this->assertSame('', $row['sync_claim']);

        // not due yet: nothing happens
        $this->proxy['down'] = false;
        $this->assertSame(0, $this->synchronizer()->run()->processed());

        // due again (or "--now"): transferred
        $this->queue->resetDelays();
        $this->assertSame(1, $this->synchronizer()->run()->indexed);
        $this->assertSame(SyncStatus::DONE, $this->row($id)['sync_status']);
    }

    public function testRejectedDocumentIsRetriedWithBackoffAndFailsAfterMaxAttempts(): void
    {
        $bad = $this->repository->save($this->document('https://x.test/bad'))->id;
        $good = $this->repository->save($this->document('https://x.test/good'))->id;
        $this->proxy['reject'] = [(string) $bad => 'mapper_parsing_exception'];

        $report = $this->synchronizer()->run();

        $this->assertSame(1, $report->indexed);
        $this->assertSame(1, $report->failed);
        $this->assertSame(SyncStatus::DONE, $this->row($good)['sync_status']);
        $row = $this->row($bad);
        $this->assertSame(SyncStatus::PENDING, $row['sync_status']);
        $this->assertSame(1, (int) $row['sync_attempts']);
        $this->assertEqualsWithDelta(time() + 60, (int) $row['sync_next_at'], 5);
        $this->assertStringContainsString('mapper_parsing_exception', $row['sync_error']);

        // attempts 2 and 3 (max_attempts = 3): exponential backoff, then failed
        $this->connection->executeStatement('UPDATE tl_indices SET sync_next_at=0 WHERE id=?', [$bad]);
        $this->synchronizer()->run();
        $this->assertEqualsWithDelta(time() + 120, (int) $this->row($bad)['sync_next_at'], 5);

        $this->connection->executeStatement('UPDATE tl_indices SET sync_next_at=0 WHERE id=?', [$bad]);
        $report = $this->synchronizer()->run();

        $this->assertSame(1, $report->failedPermanently);
        $this->assertSame(SyncStatus::FAILED, $this->row($bad)['sync_status']);
        $this->assertSame(0, $this->synchronizer()->run()->processed(), 'failed records are not retried automatically');

        // "--retry-failed" after the cause was fixed
        $this->proxy['reject'] = [];
        $this->assertSame(1, $this->queue->retryFailed());
        $this->assertSame(1, $this->synchronizer()->run()->indexed);
    }

    public function testRejectedRequestIsBisectedToFindTheBrokenDocument(): void
    {
        $ids = [];

        foreach (range(1, 4) as $i) {
            $ids[] = $this->repository->save($this->document('https://x.test/p'.$i))->id;
        }

        // the proxy rejects every request that contains document 3 as a whole (e.g. 413/422)
        $this->proxy['rejectRequestWith'] = (string) $ids[2];

        $report = $this->synchronizer()->run();

        $this->assertSame(3, $report->indexed);
        $this->assertSame(1, $report->failed);
        $this->assertSame(SyncStatus::PENDING, $this->row($ids[2])['sync_status']);
        $this->assertSame(1, (int) $this->row($ids[2])['sync_attempts']);
    }

    public function testChangeDuringTransferIsSentAgain(): void
    {
        $id = $this->repository->save($this->document(text: 'Version 1'))->id;

        // the crawler stores a new version while the bulk request is running
        $this->proxy['onBulk'] = function () {
            $this->proxy['onBulk'] = null;
            $this->repository->save($this->document(text: 'Version 2'));
        };

        $report = $this->synchronizer()->run(maxBatches: 1);

        $this->assertSame(0, $report->indexed);
        $this->assertSame(1, $report->superseded);
        $this->assertSame(SyncStatus::PENDING, $this->row($id)['sync_status']);
        $this->assertSame('', $this->row($id)['sync_claim']);

        $this->assertSame(1, $this->synchronizer()->run()->indexed);
        $this->assertSame('Version 2', $this->lastBulk()[0]['document']['text']);
        $this->assertSame(SyncStatus::DONE, $this->row($id)['sync_status']);
        $this->assertSame(2, (int) $this->row($id)['synced_revision']);
    }

    public function testDeletionDuringTransferIsProcessedAfterwards(): void
    {
        $id = $this->repository->save($this->document())->id;

        $this->proxy['onBulk'] = function () {
            $this->proxy['onBulk'] = null;
            $this->repository->markDeletedByUrl('https://x.test/kita');
        };

        $this->assertSame(1, $this->synchronizer()->run(maxBatches: 1)->superseded);
        $this->assertSame(States::DELETE, $this->row($id)['state']);

        $report = $this->synchronizer()->run();

        $this->assertSame(1, $report->deleted);
        $this->assertSame(['action' => 'delete', 'id' => (string) $id], $this->lastBulk()[0]);
        $this->assertSame([], $this->row($id));
        $this->assertSame(0, $this->chunkCount($id));
    }

    public function testConstantlyChangingRecordDoesNotLoopForever(): void
    {
        $id = $this->repository->save($this->document(text: 'Version 0'))->id;
        $version = 0;
        $this->proxy['onBulk'] = function () use (&$version) {
            $this->repository->save($this->document(text: 'Version '.++$version));
        };

        $report = $this->synchronizer()->run();

        // the record is not claimed again within the same run, the next run sends the newest version
        $this->assertSame(1, $report->batches);
        $this->assertSame(1, $report->superseded);
        $this->assertSame(SyncStatus::PENDING, $this->row($id)['sync_status']);
        $this->assertSame('', $this->row($id)['sync_claim']);
    }

    public function testDeletionRemovesTheDocumentFromIndexAndDatabase(): void
    {
        $id = $this->repository->save($this->document(text: $this->words(700)))->id;
        $this->synchronizer()->run();
        $this->repository->markDeletedByUrl('https://x.test/kita');

        $report = $this->synchronizer()->run();

        $this->assertSame(1, $report->deleted);
        $this->assertSame([], $this->row($id));
        $this->assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_indices_chunks'));
    }

    public function testDeletingADocumentThatIsNotInTheIndexSucceeds(): void
    {
        $id = $this->repository->save($this->document())->id;
        $this->repository->markDeletedByUrl('https://x.test/kita');
        $this->proxy['notFound'] = [(string) $id];

        $this->assertSame(1, $this->synchronizer()->run()->deleted);
        $this->assertSame([], $this->row($id));
    }

    public function testProcessingIsIdempotent(): void
    {
        $this->repository->save($this->document('https://x.test/a'));
        $this->repository->save($this->document('https://x.test/b'));

        $this->assertSame(2, $this->synchronizer()->run()->indexed);
        $requests = \count($this->requests);

        // nothing pending: no request at all
        $this->assertSame(0, $this->synchronizer()->run()->processed());
        $this->assertCount($requests, $this->requests);

        // unchanged crawl: nothing queued
        $this->repository->save($this->document('https://x.test/a'));
        $this->assertSame(0, $this->synchronizer()->run()->processed());

        // manual re-index sends the same document again ("index" replaces it, no duplicates)
        $this->repository->requeue([(int) $this->connection->fetchOne("SELECT id FROM tl_indices WHERE url='https://x.test/a'")]);
        $this->assertSame(1, $this->synchronizer()->run()->indexed);
        $this->assertSame('index', $this->lastBulk()[0]['action']);
    }

    public function testClaimedRecordsAreNotProcessedTwice(): void
    {
        $id = $this->repository->save($this->document())->id;

        // another worker holds the record
        $this->assertCount(1, $this->queue->claim('other-worker', 10));
        $this->assertSame(0, $this->synchronizer()->run()->processed());

        // its claim expires (crashed worker)
        $this->connection->executeStatement('UPDATE tl_indices SET sync_claimed_until=? WHERE id=?', [time() - 1, $id]);
        $this->assertSame(1, $this->synchronizer()->run()->indexed);
    }

    public function testMovedDocumentIsRemovedFromTheOldIndex(): void
    {
        $id = $this->repository->save($this->document())->id;
        $this->synchronizer()->run();

        // the page was moved to another root page (other index)
        $this->repository->save($this->document(overrides: ['rootId' => 10, 'pageId' => 11]));
        $this->requests = [];
        $this->synchronizer()->run();

        $this->assertSame(['action' => 'delete', 'id' => (string) $id], $this->request('POST', '/api/v1/indices/'.self::INDEX.'/documents/_bulk')['body']['operations'][0]);
        $this->assertNotNull($this->request('POST', '/api/v1/indices/contao_search_sig_10/documents/_bulk'));
        $this->assertSame('contao_search_sig_10', $this->row($id)['synced_index']);
    }

    public function testBatchesAndRequestSizeAreLimited(): void
    {
        foreach (range(1, 7) as $i) {
            $this->repository->save($this->document('https://x.test/p'.$i, $this->words(300)));
        }

        $report = $this->synchronizer(batchSize: 3, maxRequestBytes: 5000)->run();

        $this->assertSame(7, $report->indexed);
        $this->assertSame(3, $report->batches);

        foreach ($this->requests as $request) {
            if (str_ends_with($request['path'], '/_bulk')) {
                $this->assertLessThanOrEqual(3, \count($request['body']['operations']));
            }
        }
    }

    private function synchronizer(int $batchSize = 50, int $maxRequestBytes = 8 * 1024 * 1024): IndexSynchronizer
    {
        $licenseProvider = new LicenseProvider(new class extends Credentials {
            public function getCredentials(): array|bool
            {
                return ['id' => 1, 'type' => 'licence', 'signature' => 'sig', 'key' => 'LIC', 'keys' => '', 'authToken' => '', 'singleDocument' => ''];
            }
        });
        $proxy = new ProxyClient(new MockHttpClient($this->respond(...)), 'https://proxy.test');
        $indexNames = new IndexNameResolver($licenseProvider);
        $indexing = new IndexingService($proxy, $licenseProvider, $indexNames, new MappingFactory(), new DocumentFactory());

        return new IndexSynchronizer(
            $this->queue,
            $this->repository,
            new DocumentPayloadBuilder(),
            $proxy,
            $licenseProvider,
            $indexNames,
            $indexing,
            new EngineCapabilities($proxy, $licenseProvider, $this->connection),
            null,
            $batchSize,
            $maxRequestBytes,
            60,
            static fn (array $row): array => ['analyzer' => 'german', 'language' => $row['language']],
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function respond(string $method, string $url, array $options): MockResponse
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $body = isset($options['body']) ? json_decode((string) $options['body'], true) : null;
        $this->requests[] = ['method' => $method, 'path' => $path, 'body' => $body];

        if ($this->proxy['down']) {
            return new MockResponse('', ['error' => 'Connection refused']);
        }

        if (str_ends_with($path, '/api/v1/engine')) {
            return match ($this->proxy['engine']) {
                'opensearch3' => new MockResponse('{"product":"opensearch","version":"3.0.0","reachable":true,"supports":{"nested":true,"chunks":true,"vectors":true}}'),
                'opensearch2' => new MockResponse('{"product":"opensearch","version":"2.19.0","reachable":true,"supports":{"nested":true,"chunks":false,"vectors":false}}'),
                'elasticsearch' => new MockResponse('{"product":"elasticsearch","version":"8.15.0","reachable":true,"supports":{"nested":true,"chunks":false,"vectors":false}}'),
                default => new MockResponse('{"product":"unknown","version":"","reachable":false,"supports":{"nested":false,"chunks":false,"vectors":false},"error":"down"}', ['http_code' => 503]),
            };
        }

        if ('PUT' === $method && preg_match('#/api/v1/indices/[^/]+$#', $path)) {
            return new MockResponse(json_encode(['created' => !$this->proxy['indexExists']]));
        }

        if (str_ends_with($path, '/mapping')) {
            return new MockResponse('GET' === $method ? json_encode(['mappings' => $this->proxy['mapping'] ?: new \stdClass()]) : '{"acknowledged":true}');
        }

        if (str_ends_with($path, '/_bulk')) {
            if (null !== $this->proxy['onBulk']) {
                ($this->proxy['onBulk'])();
            }

            $ids = array_column($body['operations'], 'id');

            if (isset($this->proxy['rejectRequestWith']) && \in_array($this->proxy['rejectRequestWith'], $ids, true)) {
                return new MockResponse('{"error":{"code":"payload_too_large","message":"too large"}}', ['http_code' => 413]);
            }

            $items = [];

            foreach ($body['operations'] as $operation) {
                $id = $operation['id'];
                $items[] = match (true) {
                    isset($this->proxy['reject'][$id]) => ['action' => $operation['action'], 'id' => $id, 'status' => 400, 'error' => ['type' => $this->proxy['reject'][$id], 'reason' => 'failed to parse']],
                    \in_array($id, $this->proxy['notFound'] ?? [], true) => ['action' => 'delete', 'id' => $id, 'status' => 404, 'result' => 'not_found'],
                    default => ['action' => $operation['action'], 'id' => $id, 'status' => 'delete' === $operation['action'] ? 200 : 201, 'result' => 'delete' === $operation['action'] ? 'deleted' : 'created'],
                };
            }

            $errors = (bool) array_filter($items, static fn (array $item): bool => $item['status'] >= 300 && 404 !== $item['status']);

            return new MockResponse(json_encode(['errors' => $errors, 'items' => $items]), ['http_code' => $errors ? 207 : 200]);
        }

        return new MockResponse('{}');
    }

    /**
     * @return array{method: string, path: string, body: mixed}|null
     */
    private function request(string $method, string $pathPart): ?array
    {
        foreach (array_reverse($this->requests) as $request) {
            if ($method === $request['method'] && str_contains($request['path'], $pathPart)) {
                return $request;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lastBulk(): array
    {
        return $this->request('POST', '/_bulk')['body']['operations'] ?? [];
    }
}
