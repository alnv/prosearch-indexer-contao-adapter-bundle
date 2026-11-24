<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexSchemaResolver;
use Contao\System;

class Options
{

    /**
     * @var array|bool
     */
    protected array|bool $arrCredentials = [];

    /**
     * @var string
     */
    protected string $analyzer = 'contao';

    /**
     * @var string
     */
    protected string $language = '';

    /**
     * @var array
     */
    protected array $categories = [];

    /**
     * @var string
     */
    protected string $domain = '';

    /**
     * @var bool
     */
    protected bool $fuzzy = false;

    /**
     * @var bool
     */
    protected bool $openDocumentsInBrowser = true;

    /**
     * @var int
     */
    protected int $rootPageId = 0;

    /**
     * @var int
     */
    protected int $minKeywordLength = 0;

    /**
     * @var int
     */
    protected int $perPage = 100;

    public function __construct()
    {
        $objCredentials = new Credentials();
        $this->arrCredentials = $objCredentials->getCredentials();
    }

    /**
     * @param string $strAnalyzer
     * @return void
     */
    public function setAnalyzer(string $strAnalyzer = ''): void
    {
        // empty: setting of the root page (call setRootPageId() first), otherwise of the credentials
        if (!$strAnalyzer) {
            $container = System::getContainer();
            $strAnalyzer = $container->has(IndexSchemaResolver::class)
                ? $container->get(IndexSchemaResolver::class)->getConfiguredAnalyzer($this->rootPageId)
                : (string)(($this->arrCredentials ?: [])['analyzer'] ?? '');
        }

        $this->analyzer = $strAnalyzer ?: IndexSchemaResolver::DEFAULT_ANALYZER;
    }

    /**
     * @param string $strLanguage
     * @return void
     */
    public function setLanguage(string $strLanguage = ''): void
    {
        $this->language = $strLanguage;
    }

    /**
     * @param array $arrCategories
     * @return void
     */
    public function setCategories(array $arrCategories = []): void
    {
        $this->categories = $arrCategories;
    }

    /**
     * @param $strRootId
     * @return void
     */
    public function setRootPageId($strRootId = null): void
    {

        $strUseSingleDocument = (bool)($this->arrCredentials['singleDocument'] ?? '');
        if ($strUseSingleDocument) {
            return;
        }

        $this->rootPageId = (int)$strRootId;
    }

    /**
     * @param int $strPerPage
     * @return void
     */
    public function setPerPage(int $strPerPage = 100): void
    {
        $this->perPage = $strPerPage;
    }

    /**
     * @param string $strDomains
     * @return void
     */
    public function setDomain(string $strDomains = ''): void
    {
        $this->domain = $strDomains ?: '';
    }

    /**
     * @param bool $blnFuzzy
     * @return void
     */
    public function setFuzzy(bool $blnFuzzy): void
    {
        $this->fuzzy = $blnFuzzy;
    }

    /**
     * @param bool $blnOpenDocumentsInBrowser
     * @return void
     */
    public function setOpenDocumentsInBrowser(bool $blnOpenDocumentsInBrowser = true): void
    {
        $this->openDocumentsInBrowser = $blnOpenDocumentsInBrowser;
    }

    /**
     * @param int $wordLength
     * @return void
     */
    public function setMinKeywordLength(int $wordLength = 4): void
    {
        $this->minKeywordLength = $wordLength;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {

        return [
            'analyzer' => $this->analyzer,
            'language' => $this->language,
            'rootPageId' => $this->rootPageId,
            'perPage' => $this->perPage,
            'fuzzy' => $this->fuzzy,
            'openDocumentsInBrowser' => $this->openDocumentsInBrowser,
            'minKeywordLength' => $this->minKeywordLength,
            'domain' => $this->domain,
            'categories' => $this->categories
        ];
    }
}