<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Purge;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;

class PurgeElasticsearch
{

    public function deleteAllDatabases()
    {

        $objElasticsearch = new Elasticsearch((new Options())->getOptions());
        $objElasticsearch->deleteDatabases();
    }
}