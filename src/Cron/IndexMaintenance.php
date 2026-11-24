<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Cron;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexQueue;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\EngineCapabilities;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Contao\CoreBundle\Framework\ContaoFramework;
use Psr\Log\LoggerInterface;

class IndexMaintenance
{
    public function __construct(
        private readonly ContaoFramework    $framework,
        private readonly IndicesRepository  $repository,
        private readonly IndexQueue         $queue,
        private readonly EngineCapabilities $engine,
        private readonly LicenseProvider    $licenseProvider,
        private readonly ?LoggerInterface   $logger = null,
    )
    {
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        $orphans = $this->repository->deleteOrphanedChunks();

        if ($orphans > 0) {
            $this->logger?->info(\sprintf('ProSearch: %d orphaned chunks removed.', $orphans));
        }

        if ($this->licenseProvider->hasCredentials()) {
            $this->engine->get();
        }

        $categories = (new Categories())->setCategories();

        if (array_sum($categories) > 0) {
            $this->logger?->info(\sprintf('ProSearch: categories synchronized (%d created, %d activated, %d deactivated).', $categories['created'], $categories['activated'], $categories['deactivated']));
        }

        $failed = $this->queue->statistics()['failed'];

        if ($failed > 0) {
            $this->logger?->warning(\sprintf('ProSearch: %d documents could not be transferred to the search index (see "prosearch:index:status", retry with "prosearch:index:sync --retry-failed").', $failed));
        }
    }
}
