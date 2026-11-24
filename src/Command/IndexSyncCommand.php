<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Command;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexQueue;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexSynchronizer;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'prosearch:index:sync', description: 'Transfers pending documents (tl_indices) to the search index through the ProSearch proxy.')]
class IndexSyncCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework   $framework,
        private readonly IndexSynchronizer $synchronizer,
        private readonly IndexQueue        $queue,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Documents per batch (default: queue.batch_size)')
            ->addOption('max-batches', null, InputOption::VALUE_REQUIRED, 'Stop after n batches')
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Stop after n seconds')
            ->addOption('id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only these tl_indices IDs (queued again)')
            ->addOption('retry-failed', null, InputOption::VALUE_NONE, 'Queue failed documents again before the run')
            ->addOption('now', null, InputOption::VALUE_NONE, 'Postponed documents (proxy was unreachable) become due immediately');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->framework->initialize();

        if ($input->getOption('retry-failed')) {
            $io->note(\sprintf('%d failed documents queued again.', $this->queue->retryFailed()));
        }

        if ($input->getOption('now')) {
            $io->note(\sprintf('%d postponed documents are due now.', $this->queue->resetDelays()));
        }

        $ids = array_values(array_filter(array_map('intval', (array)$input->getOption('id'))));

        $report = $this->synchronizer->run(
            null !== $input->getOption('time-limit') ? (float)$input->getOption('time-limit') : null,
            [] !== $ids ? $ids : null,
            null !== $input->getOption('batch-size') ? max(1, (int)$input->getOption('batch-size')) : null,
            null !== $input->getOption('max-batches') ? max(1, (int)$input->getOption('max-batches')) : null,
        );

        if ($report->indexed > 0 || $report->deleted > 0) {
            (new Categories())->setCategories();
        }

        foreach ($report->warnings as $warning) {
            $io->warning($warning);
        }

        foreach ($report->errors as $error) {
            $io->error($error);
        }

        $io->definitionList(
            ['Indexed' => $report->indexed],
            ['Deleted' => $report->deleted],
            ['Changed during transfer (queued again)' => $report->superseded],
            ['Failed (retried later)' => $report->failed],
            ['Failed permanently' => $report->failedPermanently],
            ['Postponed (proxy not reachable)' => $report->postponed],
            ['Chunks sent' => $report->chunksSent],
        );

        $stats = $this->queue->statistics();
        $io->text(\sprintf('Queue: %d pending (%d due), %d failed, %d done.', $stats['pending'], $stats['due'], $stats['failed'], $stats['done']));

        return $report->proxyUnavailable || $report->failedPermanently > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}