<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Logger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexSynchronizer;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\SyncReport;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyRateLimitException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\PageModel;
use Contao\System;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class IndexingService
{
    private array $ensuredIndices = [];

    private array $preparedIndices = [];

    public function __construct(
        private readonly ProxyClient       $proxy,
        private readonly LicenseProvider   $licenseProvider,
        private readonly IndexNameResolver $indexNames,
        private readonly MappingFactory    $mappingFactory,
        private readonly DocumentFactory   $documentFactory,
        private readonly ?LoggerInterface  $contaoLogger = null,
        private readonly int               $maxRateLimitWait = 15,
        private readonly ?IndexSchemaResolver $schemaResolver = null,
    )
    {
    }

    public function indexDocuments(int|string|null $indicesId, array $options = [], ?object $hookContext = null): void
    {
        if (!$indicesId) {
            return;
        }

        $this->synchronizeNow([(int)$indicesId], false);
    }

    private function synchronizeNow(array $ids, bool $delete): ?SyncReport
    {
        $container = System::getContainer();
        $repository = $container->get(IndicesRepository::class);

        $delete ? $repository->markDeleted($ids) : $repository->requeue($ids);

        try {
            $report = $container->get(IndexSynchronizer::class)->run(ids: $ids);
        } catch (\Throwable $e) {
            Logger::set('ProSearch: transfer of ' . implode(', ', $ids) . ' failed: ' . $e->getMessage(), LogLevel::ERROR, __METHOD__);

            return null;
        }

        foreach ($report->errors as $error) {
            Logger::set('ProSearch: ' . $error, LogLevel::ERROR, __METHOD__);
        }

        return $report;
    }

    public function getIndex(int|string|null $indicesId = null, int $limit = 5): array
    {
        $columns = ['state=?'];
        $values = [States::ACTIVE];

        if ($indicesId) {
            $columns[] = 'id=?';
            $values[] = $indicesId;
        }

        $indices = IndicesModel::findAll(['column' => $columns, 'value' => $values, 'limit' => $limit, 'order' => 'last_indexed ASC']);

        if (!$indices) {
            return [];
        }

        $documents = [];

        while ($indices->next()) {
            $documents[$indices->url] = $this->documentFactory->create($indices->id);
        }

        return $documents;
    }

    public function ensureMapping(string $license, string $index, array $options = [], ?object $hookContext = null): void
    {
        if (isset($this->ensuredIndices[$index])) {
            return;
        }

        $params = $this->mappingFactory->create($index, $options, $hookContext);
        $result = $this->proxy->createIndex($license, $params['index'], $params['body']);

        if ($result['created'] ?? false) {
            $this->log('Mapping for Index ' . $params['index'] . ' was created.');
        }

        $this->ensuredIndices[$index] = true;
    }

    public function ensureIndex(string $license, string $index, array $options, bool $chunks, bool $vectors = false): bool
    {
        $key = $index . '|' . ($chunks ? 'c' : '') . ($vectors ? 'v' : '');

        if (isset($this->preparedIndices[$key])) {
            return $this->preparedIndices[$key];
        }

        $params = $this->mappingFactory->create($index, $options, null, $chunks, $vectors);
        $result = $this->proxy->createIndex($license, $params['index'], $params['body']);
        $this->ensuredIndices[$index] = true;
        $ready = $chunks;

        if ($result['created'] ?? false) {
            // searches read the analyzer of the new index from its mapping
            $this->schemaResolver?->forget($params['index']);
            $this->log('Mapping for Index ' . $params['index'] . ' was created' . ($chunks ? ' (with chunks).' : '.'));

            // the proxy removes the chunk mapping if its engine does not support chunks
            if (!empty($result['warnings'])) {
                $ready = false;
            }
        } elseif ($chunks) {
            $ready = $this->addChunkMapping($license, $index, $params['body']['mappings']['properties']);
        }

        return $this->preparedIndices[$key] = $ready;
    }

    /**
     * @param array<string, mixed> $properties target properties of the Contao mapping
     */
    private function addChunkMapping(string $license, string $index, array $properties): bool
    {
        $existing = (array)($this->proxy->getMapping($license, $index)['properties'] ?? []);
        $type = $existing['chunks']['type'] ?? null;

        if ('nested' === $type) {
            return true;
        }

        if (null !== $type || isset($existing['chunks'])) {
            // created dynamically as object by an older client: can not be changed into nested
            Logger::set('ProSearch: the index ' . $index . ' contains "chunks" without nested mapping. Run "prosearch:index:rebuild" to recreate the index; chunks are not transferred until then.', LogLevel::ERROR, __METHOD__);

            return false;
        }

        $add = ['chunks' => $properties['chunks']];

        foreach (['image', 'text'] as $field) {
            if (!isset($existing[$field]) && isset($properties[$field])) {
                $add[$field] = $properties[$field];
            }
        }

        $result = $this->proxy->updateMapping($license, $index, ['properties' => $add]);

        if (!empty($result['warnings'])) {
            return false;
        }

        $this->log('Chunk mapping added to index ' . $index . '.');

        return true;
    }

    public function forgetIndex(string $index): void
    {
        unset($this->ensuredIndices[$index]);
        $this->schemaResolver?->forget($index);

        foreach (array_keys($this->preparedIndices) as $key) {
            if (str_starts_with($key, $index . '|')) {
                unset($this->preparedIndices[$key]);
            }
        }
    }

    public function deleteIndex(int|string $indicesId): bool
    {
        if (!IndicesModel::findByPk($indicesId)) {
            return true;
        }

        $report = $this->synchronizeNow([(int)$indicesId], true);

        return null !== $report && 1 === $report->deleted;
    }

    public function deleteDatabases(): void
    {
        $roots = PageModel::findPublishedRootPages();

        if (!$roots || !$this->licenseProvider->hasCredentials()) {
            return;
        }

        $deleted = [];

        while ($roots->next()) {
            $index = $this->indexNames->getIndexName($roots->id);

            if (isset($deleted[$index])) {
                continue;
            }

            try {
                $this->proxy->deleteIndex($this->licenseProvider->getLicenseIdentifier((string)$roots->dns ?: null), $index);
                $deleted[$index] = true;
                unset($this->ensuredIndices[$index]);
            } catch (ProxyException $e) {
                Logger::set('ProSearch: deleting index ' . $index . ' failed: ' . $e->getMessage(), LogLevel::ERROR, __METHOD__);
            }
        }
    }

    private function withRateLimitRetry(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ProxyRateLimitException $e) {
            $wait = max(1, $e->getRetryAfter());

            if ($wait > $this->maxRateLimitWait) {
                throw $e;
            }

            sleep($wait);

            return $operation();
        }
    }

    private function log(string $message): void
    {
        $this->contaoLogger?->log(LogLevel::DEBUG, $message, ['contao' => new ContaoContext(__CLASS__)]);
    }
}
