<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\EngineCapabilities;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyLicenseException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyRateLimitException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyUnavailableException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexingService;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexNameResolver;
use Psr\Log\LoggerInterface;

class IndexSynchronizer
{
    private const int MAX_OPERATIONS = 500;

    private const int MAX_RATE_LIMIT_WAIT = 15;

    public function __construct(
        private readonly IndexQueue             $queue,
        private readonly IndicesRepository      $repository,
        private readonly DocumentPayloadBuilder $payloadBuilder,
        private readonly ProxyClient            $proxy,
        private readonly LicenseProvider        $licenseProvider,
        private readonly IndexNameResolver      $indexNames,
        private readonly IndexingService        $indexingService,
        private readonly EngineCapabilities     $engine,
        private readonly ?LoggerInterface       $logger = null,
        private readonly int                    $batchSize = 50,
        private readonly int                    $maxRequestBytes = 8 * 1024 * 1024,
        private readonly int                    $retryDelay = 60,
        /** @var (\Closure(array<string, mixed>): array<string, mixed>)|null adapter options of a record (mapping analyzer) */
        private readonly ?\Closure              $optionsFactory = null,
    )
    {
    }

    public function run(?float $timeLimit = null, ?array $ids = null, ?int $batchSize = null, ?int $maxBatches = null): SyncReport
    {
        $report = new SyncReport();

        if (!$this->licenseProvider->hasCredentials() || '' === $this->licenseProvider->getSignature()) {
            $report->addWarning('No ProSearch credentials configured, nothing was transferred.');

            return $report;
        }

        $start = microtime(true);
        $batchSize ??= $this->batchSize;
        $seen = [];

        do {
            $token = IndexQueue::newClaimToken();
            $rows = $this->queue->claim($token, $batchSize, $ids);

            if ([] === $rows) {
                break;
            }

            // records changed during their transfer are claimed again right away; a batch that only
            // contains records of this run means they keep changing: leave them for the next run
            $rowIds = array_map(static fn(array $row): int => (int)$row['id'], $rows);

            if ([] === array_diff($rowIds, array_keys($seen))) {
                $this->queue->release($rowIds, $token);

                break;
            }

            $seen += array_fill_keys($rowIds, true);
            ++$report->batches;

            try {
                $this->processBatch($rows, $token, $report);
            } catch (\Throwable $e) {
                // never leave claimed records behind (they would wait for the claim TTL)
                $this->queue->release(array_map(static fn(array $row): int => (int)$row['id'], $rows), $token);

                throw $e;
            }

            if (null !== $ids || $report->proxyUnavailable) {
                break;
            }

            if (null !== $maxBatches && $report->batches >= $maxBatches) {
                break;
            }

            if (null !== $timeLimit && microtime(true) - $start >= $timeLimit) {
                $report->timeLimitReached = true;

                break;
            }
        } while (true);

        if ($report->processed() > 0) {
            $this->logger?->info('ProSearch queue: ' . $report->summary());
        }

        return $report;
    }

    private function processBatch(array $rows, string $token, SyncReport $report): void
    {
        $engine = $this->engine->get();
        $groups = [];
        $moved = [];

        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $target = $this->targetIndex($row);
            $license = $this->licenseProvider->getLicenseIdentifier((string)($row['domain'] ?? '') ?: null);
            $previous = (string)($row['synced_index'] ?? '');

            if (States::DELETE === $row['state']) {
                // a record that was never transferred may still exist in the computed index (3.x data)
                $groups[$license . "\0" . ($previous ?: $target)]['delete'][$id] = $row;

                continue;
            }

            if ('' !== $previous && $previous !== $target) {
                $moved[$license . "\0" . $previous][$id] = $row;
            }

            $groups[$license . "\0" . $target]['index'][$id] = $row;
        }

        // documents that moved to another index are removed from the old one first
        foreach ($moved as $key => $movedRows) {
            [$license, $index] = explode("\0", $key, 2);
            $operations = [];

            foreach ($movedRows as $id => $row) {
                $operations[] = ['action' => 'delete', 'id' => (string)$id, '_ref' => $id];
            }

            $this->send($license, $index, $operations, $token, $report, function (int $id) use ($token): void {
                $this->queue->clearSyncedIndex($id, $token);
            }, null);

            if ($report->proxyUnavailable) {
                $this->postponeRemaining($rows, $token, $report);

                return;
            }
        }

        foreach ($groups as $key => $group) {
            [$license, $index] = explode("\0", $key, 2);
            $operations = [];
            $indexRows = $group['index'] ?? [];
            $chunks = null;

            if ([] !== $indexRows) {
                $first = reset($indexRows);

                try {
                    $chunksReady = $this->indexingService->ensureIndex($license, $index, $this->options($first), $engine->supportsChunks, $engine->supportsVectors);
                } catch (ProxyException $e) {
                    $this->handleRequestFailure($e, array_keys($indexRows), $token, $report, $index);

                    if ($report->proxyUnavailable) {
                        $this->postponeRemaining($rows, $token, $report);

                        return;
                    }

                    continue;
                }

                if ($chunksReady) {
                    $chunks = $this->repository->fetchChunks(array_keys($indexRows));
                }

                foreach ($indexRows as $id => $row) {
                    $document = $this->payloadBuilder->build($row, null !== $chunks ? ($chunks[$id] ?? []) : null);
                    $operations[] = ['action' => 'index', 'id' => (string)$id, 'document' => $document, '_ref' => $id, '_chunks' => \count($document['chunks'] ?? [])];
                }
            }

            foreach ($group['delete'] ?? [] as $id => $row) {
                $operations[] = ['action' => 'delete', 'id' => (string)$id, '_ref' => $id];
            }

            $revisions = [];

            foreach ($rows as $row) {
                $revisions[(int)$row['id']] = (int)$row['revision'];
            }

            $this->send($license, $index, $operations, $token, $report, null, $revisions);

            if ($report->proxyUnavailable) {
                $this->postponeRemaining($rows, $token, $report);

                return;
            }
        }
    }

    private function send(string $license, string $index, array $operations, string $token, SyncReport $report, ?callable $onSuccess, ?array $revisions): void
    {
        foreach ($this->split($operations) as $request) {
            if ($report->proxyUnavailable) {
                return;
            }

            $this->sendRequest($license, $index, $request, $token, $report, $onSuccess, $revisions);
        }
    }

    private function sendRequest(string $license, string $index, array $operations, string $token, SyncReport $report, ?callable $onSuccess, ?array $revisions): void
    {
        $payload = array_map(static fn(array $op): array => array_diff_key($op, ['_ref' => true, '_chunks' => true]), $operations);

        try {
            $result = $this->withRateLimitRetry(fn(): array => $this->proxy->bulk($license, $index, $payload));
        } catch (ProxyException $e) {
            $isDocumentError = !$this->isTransportFailure($e);

            // a rejected request with several documents: find the culprit by bisection
            if ($isDocumentError && \count($operations) > 1) {
                $half = intdiv(\count($operations), 2);
                $this->sendRequest($license, $index, \array_slice($operations, 0, $half), $token, $report, $onSuccess, $revisions);

                if (!$report->proxyUnavailable) {
                    $this->sendRequest($license, $index, \array_slice($operations, $half), $token, $report, $onSuccess, $revisions);
                }

                return;
            }

            $this->handleRequestFailure($e, array_map(static fn(array $op): int => (int)$op['_ref'], $operations), $token, $report, $index);

            return;
        }

        foreach ((array)($result['warnings'] ?? []) as $warning) {
            $report->addWarning((string)$warning);
            $this->logger?->warning('ProSearch proxy: ' . $warning);
        }

        $items = array_values((array)($result['items'] ?? []));

        foreach ($operations as $i => $operation) {
            $id = (int)$operation['_ref'];
            $item = (array)($items[$i] ?? []);
            $status = (int)($item['status'] ?? 0);
            $isDelete = 'delete' === $operation['action'];
            $ok = ($status >= 200 && $status < 300) || ($isDelete && 404 === $status);

            if (!$ok) {
                $reason = [] === $item ? 'no result for the document' : trim(implode(': ', array_filter([(string)($item['error']['type'] ?? ''), (string)($item['error']['reason'] ?? '')])));
                $reason = '' !== $reason ? $reason . ' (HTTP ' . $status . ')' : 'HTTP ' . $status;
                $this->failDocument($id, $token, \sprintf('%s (%s): %s', $isDelete ? 'Delete' : 'Index', $index, $reason), $report);

                continue;
            }

            if (null !== $onSuccess) {
                $onSuccess($id);

                continue;
            }

            $revision = (int)($revisions[$id] ?? 0);

            if ($isDelete) {
                $this->queue->confirmDeleted($id, $revision, $token) ? ++$report->deleted : ++$report->superseded;

                continue;
            }

            if ($this->queue->confirmIndexed($id, $revision, $token, $index)) {
                ++$report->indexed;
                $report->chunksSent += (int)($operation['_chunks'] ?? 0);
            } else {
                ++$report->superseded;
            }
        }
    }

    private function handleRequestFailure(ProxyException $e, array $ids, string $token, SyncReport $report, string $index): void
    {
        if ($this->isTransportFailure($e)) {
            $report->proxyUnavailable = true;
            $report->addError($e->getMessage());
            $report->postponed += $this->queue->postpone($ids, $token, $e->getMessage(), $this->postponeDelay($e));
            $this->logger?->error('ProSearch queue: transfer postponed, ' . $e->getMessage());

            return;
        }

        foreach ($ids as $id) {
            $this->failDocument($id, $token, \sprintf('Request to %s rejected (HTTP %d): %s', $index, $e->getStatusCode(), $e->getMessage()), $report);
        }
    }

    private function failDocument(int $id, string $token, string $error, SyncReport $report): void
    {
        $report->addError('ID ' . $id . ': ' . $error);

        if ($this->queue->fail($id, $token, $error)) {
            ++$report->failedPermanently;
            $this->logger?->error(\sprintf('ProSearch queue: document %d failed %d times and is not retried automatically: %s', $id, $this->queue->getMaxAttempts(), $error));
        } else {
            ++$report->failed;
        }
    }

    private function postponeRemaining(array $rows, string $token, SyncReport $report): void
    {
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
        // only rows still claimed by this token are affected
        $report->postponed += $this->queue->postpone($ids, $token, 'Postponed: the ProSearch proxy was not reachable.', $this->retryDelay);
    }

    private function isTransportFailure(ProxyException $e): bool
    {
        return $e instanceof ProxyUnavailableException
            || $e instanceof ProxyRateLimitException
            || $e instanceof ProxyLicenseException
            || 0 === $e->getStatusCode()
            || $e->getStatusCode() >= 500
            || \in_array($e->getStatusCode(), [401, 403, 408], true);
    }

    private function postponeDelay(ProxyException $e): int
    {
        if ($e instanceof ProxyRateLimitException) {
            return max($this->retryDelay, $e->getRetryAfter());
        }

        return $this->retryDelay;
    }

    private function withRateLimitRetry(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ProxyRateLimitException $e) {
            if (max(1, $e->getRetryAfter()) > self::MAX_RATE_LIMIT_WAIT) {
                throw $e;
            }

            sleep(max(1, $e->getRetryAfter()));

            return $operation();
        }
    }

    private function split(array $operations): array
    {
        $requests = [];
        $current = [];
        $bytes = 0;

        foreach ($operations as $operation) {
            $size = \strlen((string)json_encode($operation['document'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)) + 100;

            if ([] !== $current && ($bytes + $size > $this->maxRequestBytes || \count($current) >= self::MAX_OPERATIONS)) {
                $requests[] = $current;
                $current = [];
                $bytes = 0;
            }

            $current[] = $operation;
            $bytes += $size;
        }

        if ([] !== $current) {
            $requests[] = $current;
        }

        return $requests;
    }

    private function targetIndex(array $row): string
    {
        $root = (int)($row['rootId'] ?? 0);

        if ($root <= 0 && (int)($row['pageId'] ?? 0) > 0) {
            $root = (int)$this->indexNames->getRootIdentifierFromIndicesId((int)$row['id']);
        }

        return $this->indexNames->getIndexName($root);
    }

    private function options(array $row): array
    {
        if (null !== $this->optionsFactory) {
            return ($this->optionsFactory)($row);
        }

        $options = new Options();
        $options->setLanguage((string)($row['language'] ?? ''));
        $options->setRootPageId((int)($row['rootId'] ?? 0));
        // analyzer of the root page (tl_page.psAnalyzer), used when the index is created
        $options->setAnalyzer();

        return $options->getOptions();
    }
}