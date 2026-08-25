<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Purge;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Adapter;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;

class PurgeElasticsearch
{

    public function deleteAllDatabases(): void
    {
        $objElasticsearch = (new Adapter())->getInstance((new Options())->getOptions());
        $objElasticsearch->deleteDatabases();
    }
}