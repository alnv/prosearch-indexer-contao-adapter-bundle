<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Cron;

use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsLogger;
use Psr\Log\LoggerInterface;

class PurgeSearchStatsLog
{
    public function __construct(private readonly SearchStatsLogger $statsLogger, private readonly ?LoggerInterface $logger = null)
    {
    }

    public function __invoke(): void
    {
        $deleted = $this->statsLogger->purge();

        if ($deleted > 0) {
            $this->logger?->info(\sprintf('ProSearch: %d search log entries older than %d days deleted.', $deleted, $this->statsLogger->getRetentionDays()));
        }
    }
}
