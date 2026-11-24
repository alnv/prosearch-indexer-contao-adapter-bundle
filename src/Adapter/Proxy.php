<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Logger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\SearchService;
use Contao\System;
use Psr\Log\LogLevel;

class Proxy
{
    protected string $strLicence = '';

    public function __construct($strLicence)
    {
        trigger_deprecation('alnv/prosearch-indexer-contao-adapter-bundle', '4.0', 'Using "%s" is deprecated, use "%s" instead.', __CLASS__, ProxyClient::class);

        $this->strLicence = $strLicence ?: '';
    }

    public function indexDocument($arrParams): bool
    {
        return $this->call(fn(ProxyClient $c) => $c->upsertDocument($this->strLicence, (string)$arrParams['index'], (string)$arrParams['id'], (array)$arrParams['body']));
    }

    public function deleteDatabase($strIndex): bool
    {
        return $this->call(fn(ProxyClient $c) => $c->deleteIndex($this->strLicence, (string)$strIndex));
    }

    public function deleteDocument($strIndex, $strDocumentId): bool
    {
        return $this->call(fn(ProxyClient $c) => $c->deleteDocument($this->strLicence, (string)$strIndex, (string)$strDocumentId));
    }

    public function indexMapping($arrParams): bool
    {
        return $this->call(fn(ProxyClient $c) => $c->createIndex($this->strLicence, (string)$arrParams['index'], (array)($arrParams['body'] ?? [])));
    }

    public function search($arrKeywords, $strIndex, $arrOptions)
    {
        return System::getContainer()->get(SearchService::class)->search((array)$arrKeywords, (array)$arrOptions, (string)$strIndex);
    }

    public function autocompletion($arrKeywords, $strIndex, $arrOptions)
    {
        return System::getContainer()->get(SearchService::class)->autocomplete((array)$arrKeywords, (array)$arrOptions, (string)$strIndex);
    }

    private function call(\Closure $operation): bool
    {
        try {
            $operation(System::getContainer()->get(ProxyClient::class));
        } catch (ProxyException $e) {
            Logger::set($e->getMessage(), LogLevel::ERROR, __CLASS__);

            return false;
        }

        return true;
    }
}