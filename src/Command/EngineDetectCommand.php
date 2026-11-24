<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Command;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\EngineCapabilities;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'prosearch:engine:detect', description: 'Detects the search engine behind the proxy (OpenSearch/Elasticsearch, version, chunk support) and stores it in the credentials.')]
class EngineDetectCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework    $framework,
        private readonly EngineCapabilities $engine,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->framework->initialize();

        $state = $this->engine->refresh();

        $io->definitionList(
            ['Search engine' => $state->describe()],
            ['Chunks (nested)' => $state->supportsChunks ? 'yes' : 'no'],
            ['Vector search (k-NN)' => $state->supportsVectors ? 'yes' : 'no'],
        );

        if ('' !== $state->error) {
            $io->error($state->error);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
