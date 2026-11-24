<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\DocumentFactory;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexingService;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexNameResolver;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\MappingFactory;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\SearchService;
use Contao\System;

class ProxyAdapter extends AbstractAdapter
{
    public const string INDEX = IndexNameResolver::INDEX_PREFIX;

    protected string $strSignature = '';

    protected array|bool $arrCredentials = [];

    /**
     * Loads credentials and the license identifier for the current host.
     */
    public function connect(): void
    {
        $licenseProvider = $this->service(LicenseProvider::class);
        $this->arrCredentials = $licenseProvider->getCredentials();
        $this->strSignature = $licenseProvider->getSignature();
        $this->strLicense = $licenseProvider->getLicenseIdentifier();
        $this->objClient = (false !== $this->arrCredentials && '' !== $this->strSignature) ? $this->service(ProxyClient::class) : null;
    }

    /**
     * The proxy client, or null without credentials/signature (same contract as before).
     */
    public function getClient(): ?ProxyClient
    {
        return $this->objClient;
    }

    public function search($arrKeywords, string $strIndexName = '', int $intTryCounts = 0): array
    {
        if (!$strIndexName) {
            $strIndexName = $this->getIndexName($this->arrOptions['rootPageId'] ?? 0);
        }

        return $this->service(SearchService::class)->search((array)$arrKeywords, $this->arrOptions, $strIndexName);
    }

    public function autoCompilation($arrKeywords, string $strIndexName = ''): array
    {
        if (!$strIndexName) {
            $strIndexName = $this->getIndexName($this->arrOptions['rootPageId'] ?? 0);
        }

        return $this->service(SearchService::class)->autocomplete((array)$arrKeywords, $this->arrOptions, $strIndexName);
    }

    public function indexDocuments($strIndicesId): void
    {
        $this->service(IndexingService::class)->indexDocuments($strIndicesId, $this->arrOptions, $this);
    }

    public function getIndex($strIndicesId = null, int $intLimit = 5): array
    {
        return $this->service(IndexingService::class)->getIndex($strIndicesId, $intLimit);
    }

    public function createDocument($strIndicesId): bool|array
    {
        return $this->service(DocumentFactory::class)->create($strIndicesId);
    }

    public function deleteIndex($strIndicesId): void
    {
        $this->service(IndexingService::class)->deleteIndex($strIndicesId);
    }

    public function deleteDatabases(): void
    {
        $this->service(IndexingService::class)->deleteDatabases();
    }

    /**
     * Former "direct" write: creates or updates a document. $arrParams = ['index' => …, 'id' => …, 'body' => [...]].
     */
    public function clientIndex($arrParams): bool
    {
        try {
            $this->service(ProxyClient::class)->upsertDocument($this->license(), (string)$arrParams['index'], (string)$arrParams['id'], (array)$arrParams['body']);
        } catch (ProxyException) {
            return false;
        }

        return true;
    }

    /**
     * Former "direct" mapping: creates the index if it does not exist. $arrParams = ['index' => …, 'body' => [...]].
     */
    public function clientMapping($arrParams): void
    {
        $this->service(ProxyClient::class)->createIndex($this->license(), (string)$arrParams['index'], (array)($arrParams['body'] ?? []));
    }

    public function clientDelete($strIndex, $strIndicesId): void
    {
        $this->service(ProxyClient::class)->deleteDocument($this->license(), (string)$strIndex, (string)$strIndicesId);
    }

    public function getAnalyzer(): array
    {
        return $this->service(MappingFactory::class)->getAnalyzers();
    }

    public function getLicense(): string
    {
        return $this->strLicense;
    }

    private function license(): string
    {
        return $this->strLicense ?: $this->service(LicenseProvider::class)->getLicenseIdentifier();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        return System::getContainer()->get($id);
    }
}
