<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Cron;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexSynchronizer;
use Contao\CoreBundle\Framework\ContaoFramework;

class SyncIndexQueue
{
    public function __construct(
        private readonly ContaoFramework   $framework,
        private readonly IndexSynchronizer $synchronizer,
        private readonly float             $timeLimit = 20.0,
        private readonly bool              $enabled = true,
    )
    {
    }

    public function __invoke(string $scope = ''): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->framework->initialize();
        $report = $this->synchronizer->run('cli' === $scope || \PHP_SAPI === 'cli' ? max($this->timeLimit, 50.0) : $this->timeLimit);

        // new or no longer used categories (writes only if something changed)
        if ($report->indexed > 0 || $report->deleted > 0) {
            (new Categories())->setCategories();
        }
    }
}
