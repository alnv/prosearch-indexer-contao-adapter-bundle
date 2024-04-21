<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Cron;

use Contao\Database;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;

class DeleteIndices
{
    public function __invoke(): void
    {
        $objElasticsearch = new Elasticsearch((new Options())->getOptions());
        $objIndices = Database::getInstance()->prepare('SELECT * FROM tl_indices WHERE state=? ORDER BY RAND()')->limit(50)->execute(States::DELETE);

        while ($objIndices->next()) {
            $objElasticsearch->deleteIndex($objIndices->id);
        }
    }
}