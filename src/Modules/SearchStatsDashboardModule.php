<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Modules;

use Alnv\ProSearchIndexerContaoAdapterBundle\Controller\Backend\SearchStatsController;
use Contao\BackendModule;
use Contao\BackendUser;
use Contao\Environment;
use Contao\System;

class SearchStatsDashboardModule extends BackendModule
{
    private const ASSET_DIR = 'bundles/alnvprosearchindexercontaoadapter/';

    protected $strTemplate = 'be_ps_search_dashboard';

    protected function compile(): void
    {
        $container = System::getContainer();
        $router = $container->get('router');

        $this->Template->config = [
            'endpoints' => [
                'overview' => $router->generate('prosearch_search_stats_overview'),
                'terms' => $router->generate('prosearch_search_stats_terms'),
                'term' => $router->generate('prosearch_search_stats_term'),
                'export' => $router->generate('prosearch_search_stats_export'),
            ],
            // Link to the module "Suchprotokoll" (single searches, export, clear) for users who may open it
            'logUrl' => BackendUser::getInstance()->hasAccess('stats', 'modules') ? $router->generate('contao_backend', ['do' => 'stats']) : null,
            'language' => str_starts_with((string)($GLOBALS['TL_LANGUAGE'] ?? 'de'), 'de') ? 'de' : 'en',
            'timezone' => date_default_timezone_get(),
            'module' => SearchStatsController::MODULE,
        ];

        $this->Template->chartJs = $this->assetUrl('chart.umd.min.js');
        $this->Template->dashboardJs = $this->assetUrl('search-dashboard.js');
        $this->Template->dashboardCss = $this->assetUrl('search-dashboard.css');
    }

    /**
     * Public URL with the file modification time as version (cache busting after updates).
     */
    private function assetUrl(string $file): string
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');
        $webDir = System::getContainer()->hasParameter('contao.web_dir') ? (string)System::getContainer()->getParameter('contao.web_dir') : $projectDir . '/public';
        $mtime = @filemtime($webDir . '/' . self::ASSET_DIR . $file) ?: @filemtime(__DIR__ . '/../Resources/public/' . $file) ?: 0;

        return Environment::get('path') . '/' . self::ASSET_DIR . $file . '?v=' . $mtime;
    }
}
