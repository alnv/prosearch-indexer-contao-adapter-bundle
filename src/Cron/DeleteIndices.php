<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Cron;

use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexSynchronizer;
use Contao\CoreBundle\Framework\ContaoFramework;

/**
 * @deprecated since 4.0: deletions are part of the transfer queue (cron job SyncIndexQueue). The
 *             class is kept for code that calls it directly and now processes the whole queue
 *             (records marked for deletion are removed from the index and from tl_indices).
 */
class DeleteIndices
{
    public function __construct(
        private readonly ContaoFramework   $framework,
        private readonly IndexSynchronizer $synchronizer,
    )
    {
    }

    public function __invoke(): void
    {
        // contao:cron on the command line does not boot the framework
        $this->framework->initialize();
        $this->synchronizer->run(20.0);
    }
}