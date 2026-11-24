<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Command;

use Alnv\ProSearchIndexerContaoAdapterBundle\Purge\PurgeElasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Purge\PurgeException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'prosearch:index:purge', description: 'Complete reset: deletes the search indices and all local index data (documents, chunks, categories). Statistics are kept.')]
class IndexPurgeCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework    $framework,
        private readonly PurgeElasticsearch $purge,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Really delete (without it only shows what would be deleted)')
            ->addOption('local-only', null, InputOption::VALUE_NONE, 'Only reset the local data, do not touch the search engine (e.g. the cluster no longer exists)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->framework->initialize();

        $localOnly = (bool) $input->getOption('local-only');
        $counts = $this->purge->countLocal();
        $indices = $localOnly ? [] : array_keys($this->purge->getIndices());

        $io->definitionList(
            ['Indices' => $localOnly ? 'not touched (--local-only)' : (implode(', ', $indices) ?: '-')],
            ['Documents (tl_indices)' => $counts['documents']],
            ['Chunks (tl_indices_chunks)' => $counts['chunks']],
            ['Categories (tl_ps_categories)' => $counts['categories']],
            ['Kept' => 'search statistics, synonyms, credentials'],
        );

        if (!$input->getOption('force')) {
            $io->note('Nothing deleted. Run again with --force to reset.');

            return Command::SUCCESS;
        }

        try {
            $report = $this->purge->purge(!$localOnly);
        } catch (PurgeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        foreach ($report->warnings as $warning) {
            $io->warning($warning);
        }

        $io->success($report->summary().'. Re-index with the crawler (contao:crawl) to fill the index again.');

        return Command::SUCCESS;
    }
}
