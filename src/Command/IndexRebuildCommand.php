<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Command;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\IndexSynchronizer;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\EngineCapabilities;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexingService;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexNameResolver;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'prosearch:index:rebuild', description: 'Deletes and re-creates search indices with the current mapping and transfers all documents again.')]
class IndexRebuildCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly IndicesRepository $repository,
        private readonly IndexSynchronizer $synchronizer,
        private readonly IndexingService $indexingService,
        private readonly IndexNameResolver $indexNames,
        private readonly LicenseProvider $licenseProvider,
        private readonly ProxyClient $proxy,
        private readonly EngineCapabilities $engine,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('root', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only the index of these root pages')
            ->addOption('requeue-only', null, InputOption::VALUE_NONE, 'Do not delete the index, only transfer all documents again (e.g. to add chunks)')
            ->addOption('no-sync', null, InputOption::VALUE_NONE, 'Only queue the documents; the cron job transfers them')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Required to delete indices')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->framework->initialize();

        if (!$this->licenseProvider->hasCredentials()) {
            $io->error('No ProSearch credentials configured.');

            return Command::FAILURE;
        }

        $engine = $this->engine->refresh();
        $io->text('Search engine: '.$engine->describe().', chunks: '.($engine->supportsChunks ? 'yes' : 'no'));

        $roots = array_map('intval', (array) $input->getOption('root'));
        $groups = $this->groups($roots);

        if ([] === $groups) {
            $io->warning('No documents found.');

            return Command::SUCCESS;
        }

        $requeueOnly = (bool) $input->getOption('requeue-only');

        if (!$requeueOnly && !$input->getOption('force')) {
            $io->error('Deleting indices needs --force: '.implode(', ', array_keys($groups)).'. Use --requeue-only to keep the indices.');

            return Command::INVALID;
        }

        foreach ($groups as $index => $group) {
            if (!$requeueOnly) {
                try {
                    $this->proxy->deleteIndex($group['license'], $index);
                    $this->indexingService->forgetIndex($index);
                    $io->text('Deleted index '.$index);
                } catch (ProxyException $e) {
                    $io->error('Index '.$index.' could not be deleted: '.$e->getMessage());

                    return Command::FAILURE;
                }
            }

            $this->repository->requeue($group['ids'], !$requeueOnly);
            $io->text(\sprintf('%d documents of %s queued.', \count($group['ids']), $index));
        }

        if ($input->getOption('no-sync')) {
            return Command::SUCCESS;
        }

        $report = $this->synchronizer->run();
        $io->success('Transfer: '.$report->summary());

        foreach ($report->errors as $error) {
            $io->error($error);
        }

        return $report->proxyUnavailable ? Command::FAILURE : Command::SUCCESS;
    }

    private function groups(array $roots): array
    {
        $groups = [];
        $rows = $this->connection->iterateAssociative('SELECT id, rootId, domain FROM '.IndicesRepository::TABLE.' WHERE state=?', [States::ACTIVE]);

        foreach ($rows as $row) {
            if ([] !== $roots && !\in_array((int) $row['rootId'], $roots, true)) {
                continue;
            }

            $index = $this->indexNames->getIndexName((int) $row['rootId']);
            $groups[$index]['license'] ??= $this->licenseProvider->getLicenseIdentifier((string) $row['domain'] ?: null);
            $groups[$index]['ids'][] = (int) $row['id'];
        }

        return $groups;
    }
}