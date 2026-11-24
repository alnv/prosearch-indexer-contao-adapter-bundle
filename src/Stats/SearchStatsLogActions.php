<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Stats;

use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Contao\Message;
use Contao\System;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class SearchStatsLogActions
{
    public function __construct(
        private readonly ContaoFramework           $framework,
        private readonly SearchStatsExporter       $exporter,
        private readonly SearchStatsLogger         $statsLogger,
        private readonly CsrfTokenManagerInterface $tokenManager,
        private readonly UrlGeneratorInterface     $router,
        private readonly string                    $csrfTokenName,
    )
    {
    }

    public function export(): string
    {
        $this->checkRequestToken();

        $mode = SearchStatsExporter::MODE_TERMS === Input::get('mode') ? SearchStatsExporter::MODE_TERMS : SearchStatsExporter::MODE_LOG;

        throw new ResponseException($this->exporter->createResponse($mode, null, PHP_INT_MAX, $this->labels()));
    }

    public function clear(): string
    {
        $this->checkRequestToken();
        $this->statsLogger->clear();

        $this->framework->getAdapter(System::class)->loadLanguageFile('tl_search_stats_log');
        $this->framework->getAdapter(Message::class)->addConfirmation($GLOBALS['TL_LANG']['tl_search_stats_log']['cleared'] ?? 'The search log has been cleared.');

        throw new RedirectResponseException($this->router->generate('contao_backend', ['do' => 'stats']));
    }

    private function checkRequestToken(): void
    {
        $token = Input::get('rt');

        if (!\is_string($token) || '' === $token || !$this->tokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            throw new InvalidRequestTokenException('Invalid request token. Please reload the page and try again.');
        }
    }
    
    private function labels(): array
    {
        $this->framework->getAdapter(System::class)->loadLanguageFile('tl_search_stats_log');

        return (array)($GLOBALS['TL_LANG']['tl_search_stats_log']['csv'] ?? []);
    }
}