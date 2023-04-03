<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Cron;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;

class DeleteIndices
{
    public function __invoke()
    {
        $objElasticsearch = new Elasticsearch();
        $objIndices = \Database::getInstance()->prepare('SELECT * FROM tl_indices WHERE state=?')->limit(50)->execute(States::DELETE);

        while ($objIndices->next()) {

            $objElasticsearch->deleteIndex($objIndices->id);
        }
    }
}