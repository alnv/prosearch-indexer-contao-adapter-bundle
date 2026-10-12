<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Cron;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;

class DeleteIndices
{
    private $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        $objElasticsearch = (new Adapter())->getInstance((new Options())->getOptions());
        $objIndices = Database::getInstance()
            ->prepare('SELECT * FROM tl_indices WHERE state=? ORDER BY RAND()')
            ->limit(25)
            ->execute(States::DELETE);

        while ($objIndices->next()) {
            $objElasticsearch->deleteIndex($objIndices->id);
        }
    }
}