<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Purge;

use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyUnavailableException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexingService;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexNameResolver;
use Contao\Message;
use Contao\PurgeData;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class PurgeElasticsearch
{
    public const string CATEGORY_TABLE = 'tl_ps_categories';

    private array $withDocuments = [];

    public function __construct(
        private readonly Connection        $connection,
        private readonly ProxyClient       $proxy,
        private readonly LicenseProvider   $licenseProvider,
        private readonly IndexNameResolver $indexNames,
        private readonly ?IndexingService  $indexingService = null,
        private readonly ?LoggerInterface  $logger = null,
    )
    {
    }

    public function deleteAllDatabases(): void
    {
        try {
            $report = $this->purge();
        } catch (PurgeException $e) {
            Message::addError($e->getMessage(), PurgeData::class);

            return;
        }

        Message::addConfirmation(\sprintf(
            $GLOBALS['TL_LANG']['tl_maintenance']['prosearchPurged'] ?? 'ProSearch: %s',
            $report->summary()
        ), PurgeData::class);

        foreach ($report->warnings as $warning) {
            Message::addInfo($warning, PurgeData::class);
        }
    }

    public function getIndices(): array
    {
        if (!$this->licenseProvider->hasCredentials()) {
            return [];
        }

        $fallback = $this->licenseProvider->getLicenseIdentifier('');
        $targets = [];
        $this->withDocuments = [];

        // root pages of the documents (also those of deleted/unpublished roots) and all current root pages
        $rows = $this->connection->fetchAllAssociative('SELECT DISTINCT rootId, domain FROM ' . IndicesRepository::TABLE);
        $roots = $this->connection->fetchAllAssociative("SELECT id AS rootId, dns AS domain FROM tl_page WHERE type='root'");

        foreach ($rows as $row) {
            $this->withDocuments[$this->indexNames->getIndexName((int)$row['rootId'])] = true;
        }

        foreach ([...$rows, ...$roots] as $row) {
            $index = $this->indexNames->getIndexName((int)$row['rootId']);

            if (isset($targets[$index]) && '' !== $targets[$index]) {
                continue;
            }

            $domain = trim((string)($row['domain'] ?? ''));
            $license = '' !== $domain ? $this->licenseProvider->getLicenseIdentifier($domain) : '';
            $targets[$index] = '' !== $license ? $license : $fallback;
        }

        ksort($targets);

        return $targets;
    }

    public function countLocal(): array
    {
        return [
            'documents' => (int)$this->connection->fetchOne('SELECT COUNT(*) FROM ' . IndicesRepository::TABLE),
            'chunks' => (int)$this->connection->fetchOne('SELECT COUNT(*) FROM ' . IndicesRepository::CHUNK_TABLE),
            'categories' => (int)$this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::CATEGORY_TABLE),
        ];
    }

    public function purge(bool $remote = true): PurgeReport
    {
        $report = new PurgeReport();
        $indices = [];

        if ($remote) {
            if (!$this->licenseProvider->hasCredentials()) {
                throw new PurgeException('ProSearch: no credentials configured, the search index cannot be deleted. Nothing was reset.');
            }

            $indices = $this->getIndices();

            foreach ($indices as $index => $license) {
                // Indices without local documents (only derived from a root page, e.g. an old, unlicensed
                // domain) cannot leave orphaned local data behind: a failure there is only a warning.
                $hasDocuments = isset($this->withDocuments[$index]);

                if ('' === $license) {
                    if (!$hasDocuments) {
                        $report->warnings[] = \sprintf('Index %s: no license key, skipped.', $index);
                        unset($indices[$index]);

                        continue;
                    }

                    throw new PurgeException(\sprintf('ProSearch: no license key for index %s, nothing was reset.', $index));
                }

                try {
                    if ($this->proxy->deleteIndex($license, $index)) {
                        $report->deletedIndices[] = $index;
                    } else {
                        $report->missingIndices[] = $index;
                    }
                } catch (ProxyException $e) {
                    if (!$hasDocuments && !$e instanceof ProxyUnavailableException) {
                        $report->warnings[] = \sprintf('Index %s (no local documents) could not be deleted: %s', $index, $e->getMessage());
                        unset($indices[$index]);

                        continue;
                    }

                    $this->logger?->error('ProSearch reset: deleting index {index} failed: {message}', ['index' => $index, 'message' => $e->getMessage()]);

                    $deleted = [] === $report->deletedIndices ? '' : ' Already deleted: ' . implode(', ', $report->deletedIndices) . '.';

                    throw new PurgeException(\sprintf('ProSearch: index %s could not be deleted (%s). The local index data was not reset.%s', $index, $e->getMessage(), $deleted), 0, $e);
                }

                $this->indexingService?->forgetIndex($index);
            }
        } else {
            $report->remoteSkipped = true;
        }

        // one transaction: either everything local is gone or nothing (DELETE instead of TRUNCATE,
        // which would commit implicitly)
        $counts = $this->connection->transactional(static fn(Connection $connection): array => [
            'chunks' => (int)$connection->executeStatement('DELETE FROM ' . IndicesRepository::CHUNK_TABLE),
            'documents' => (int)$connection->executeStatement('DELETE FROM ' . IndicesRepository::TABLE),
            'categories' => (int)$connection->executeStatement('DELETE FROM ' . self::CATEGORY_TABLE),
        ]);

        $report->documents = $counts['documents'];
        $report->chunks = $counts['chunks'];
        $report->categories = $counts['categories'];

        // second pass: a transfer running in parallel may have recreated an index meanwhile
        foreach ($indices as $index => $license) {
            try {
                if ($this->proxy->deleteIndex($license, $index) && !\in_array($index, $report->deletedIndices, true)) {
                    $report->deletedIndices[] = $index;
                    $report->missingIndices = array_values(array_diff($report->missingIndices, [$index]));
                }
            } catch (ProxyException $e) {
                $report->warnings[] = \sprintf('Index %s: second deletion pass failed (%s). Run the reset again if documents are still found.', $index, $e->getMessage());
            }

            $this->indexingService?->forgetIndex($index);
        }

        $this->logger?->info('ProSearch reset: {summary}', ['summary' => $report->summary()]);

        return $report;
    }
}
