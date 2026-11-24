<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Command;

use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexQueue;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SyncStatus;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\EngineCapabilities;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'prosearch:index:status', description: 'Shows the transfer queue, the detected search engine and failed documents.')]
class IndexStatusCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework    $framework,
        private readonly IndexQueue         $queue,
        private readonly EngineCapabilities $engine,
        private readonly Connection         $connection,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('errors', null, InputOption::VALUE_REQUIRED, 'Number of failed/postponed documents to list', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->framework->initialize();

        $engine = $this->engine->get();
        $stats = $this->queue->statistics();

        $io->definitionList(
            ['Search engine' => $engine->describe()],
            ['Chunks transferred' => $engine->supportsChunks ? 'yes (nested)' : 'no'],
            ['Detected at' => $engine->checkedAt > 0 ? date('Y-m-d H:i:s', $engine->checkedAt) : '-'],
            ['Documents done' => $stats['done']],
            ['Pending (due now)' => \sprintf('%d (%d)', $stats['pending'], $stats['due'])],
            ['Marked for deletion' => $stats['delete']],
            ['Being transferred' => $stats['processing']],
            ['Failed' => $stats['failed']],
            ['Chunks stored' => $stats['chunks']],
        );

        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, state, sync_status, sync_attempts, sync_next_at, url, sync_error FROM " . IndicesRepository::TABLE . " WHERE sync_error<>'' AND sync_status<>? ORDER BY sync_status, id LIMIT " . max(1, (int)$input->getOption('errors')),
            [SyncStatus::DONE],
        );

        if ([] !== $rows) {
            $io->table(['ID', 'State', 'Sync', 'Attempts', 'Next try', 'URL', 'Error'], array_map(static fn(array $row): array => [
                $row['id'], $row['state'], $row['sync_status'], $row['sync_attempts'],
                (int)$row['sync_next_at'] > 0 ? date('d.m. H:i', (int)$row['sync_next_at']) : '-',
                mb_strimwidth((string)$row['url'], 0, 60, '…'), mb_strimwidth((string)$row['sync_error'], 0, 80, '…'),
            ], $rows));
        }

        return Command::SUCCESS;
    }
}