<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

/**
 *
 */
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
     * @var int
     */
    protected int $rootPageId = 0;

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
     * @return array
     */
    public function getOptions(): array
    {

        return [
            'analyzer' => $this->analyzer,
            'language' => $this->language,
            'rootPageId' => $this->rootPageId,
            'perPage' => $this->perPage
        ];
    }
}