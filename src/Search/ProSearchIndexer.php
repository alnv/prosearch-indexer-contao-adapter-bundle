<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\CoreBundle\Search\Document;
use Contao\CoreBundle\Search\Indexer\IndexerInterface;

/**
 * https://docs.contao.org/dev/framework/search-indexing/
 */
class ProSearchIndexer implements IndexerInterface
{
    public function index(Document $document): void
    {
        new Indices($document);
    }

    public function delete(Document $document): void
    {
        $strUrl = $document->getUri()->__toString();
        $objIndices = IndicesModel::findByUrl($strUrl);

        if (!$objIndices) {
            return;
        }

        $objIndices->state = States::DELETE;
        $objIndices->save();
    }

    public function clear(): void
    {
        $objIndices = IndicesModel::findAll();

        if (!$objIndices) {
            return;
        }

        while ($objIndices->next()) {
            $objIndices->state = States::DELETE;
            $objIndices->save();
        }
    }
}