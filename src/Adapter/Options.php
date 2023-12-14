<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Contao\Environment;

class Options
{
    /**
     * @var string
     */
    protected string $analyzer = 'contao';

    /**
     * @var string
     */
    protected string $language = '';

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
     * @var bool
     */
    protected bool $useUseRichSnippets = false;

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


    /**
     * @param string $strAnalyzer
     * @return void
     */
    public function setAnalyzer(string $strAnalyzer = 'contao'): void
    {

        $this->analyzer = $strAnalyzer;
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
     * @param $strRootId
     * @return void
     */
    public function setRootPageId($strRootId): void
    {

        $this->rootPageId = $strRootId;
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
     * @param string $strDomain
     * @return void
     */
    public function setDomain(string $strDomain = ""): void
    {

        $this->domain = $strDomain ?: Environment::get('host');
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
     * @param bool $blnUseRichSnippets
     * @return void
     */
    public function setUseRichSnippets(bool $blnUseRichSnippets): void
    {

        $this->useUseRichSnippets = $blnUseRichSnippets;
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
            'useUseRichSnippets' => $this->useUseRichSnippets,
            'minKeywordLength' => $this->minKeywordLength,
            'domain' => $this->domain
        ];
    }
}