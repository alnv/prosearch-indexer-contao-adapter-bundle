<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Contao\CoreBundle\Search\Document;
use Contao\CoreBundle\Search\Indexer\IndexerInterface;

/**
 * https://docs.contao.org/dev/framework/search-indexing/
 */
class ProSearchIndexer implements IndexerInterface
{
    public function index(Document $document): void
    {
        $objIndices = new Indices($document);
    }

    public function delete(Document $document): void
    {

        //
    }

    public function clear(): void
    {

        //
    }
}