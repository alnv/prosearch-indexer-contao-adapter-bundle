<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Purge;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;

class PurgeElasticsearch
{

    public function deleteAllDatabases(): void
    {

        $objElasticsearch = new Elasticsearch((new Options())->getOptions());
        $objElasticsearch->deleteDatabases();
    }
}