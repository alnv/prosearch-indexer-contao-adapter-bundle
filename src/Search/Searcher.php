<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Symfony\Component\DomCrawler\Crawler;

abstract class Searcher
{

    /**
     * @var int
     */
    protected int $indicesId = 0;

    /**
     * @var Crawler
     */
    protected Crawler $objCrawler;

    /**
     * @return int
     */
    public function getIndicesId(): int
    {
        return $this->indicesId;
    }
}