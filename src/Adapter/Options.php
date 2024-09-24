<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;

class Options
{

    /**
     * @var array
     */
    protected array $arrCredentials = [];

    /**
     * @var string
     */
    protected string $analyzer = 'contao';

    /**
     * @var string
     */
    protected string $language = '';

    /**
     * @var bool
     */
    protected bool $useOpenAi = false;

    /**
     * @var string
     */
    protected string $openAiAssistant = '';

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
    protected bool $useRichSnippets = false;

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
        if (!$strAnalyzer) {
            $strAnalyzer = $this->arrCredentials['analyzer'] ?? '';
        }

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
     * @param string $strOpenAiAssistant
     * @return void
     */
    public function setOpenAiAssistant(string $strOpenAiAssistant = ''): void
    {
        $this->openAiAssistant = $strOpenAiAssistant;
    }

    /**
     * @param bool $useAi
     * @return void
     */
    public function setUseOpenAi(bool $useAi = false): void
    {
        $this->useOpenAi = $useAi;
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
     * @param bool $blnUseRichSnippets
     * @return void
     */
    public function setUseRichSnippets(bool $blnUseRichSnippets): void
    {
        $this->useRichSnippets = $blnUseRichSnippets;
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
            'useOpenAi' => $this->useOpenAi,
            'openAiAssistant' => $this->openAiAssistant,
            'openDocumentsInBrowser' => $this->openDocumentsInBrowser,
            'useRichSnippets' => $this->useRichSnippets,
            'minKeywordLength' => $this->minKeywordLength,
            'domain' => $this->domain
        ];
    }
}