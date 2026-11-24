<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Controller\Backend;

use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchDashboard;
use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsExporter;
use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\StatsPeriod;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\System;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '%contao.backend.route_prefix%/prosearch/search-stats', name: 'prosearch_search_stats_', defaults: ['_scope' => 'backend'])]
class SearchStatsController extends AbstractController
{
    public const MODULE = 'searchdashboard';

    public function __construct(
        private readonly SearchDashboard     $dashboard,
        private readonly SearchStatsExporter $exporter,
    )
    {
    }

    #[Route(path: '/overview', name: 'overview', methods: ['GET'])]
    public function overview(Request $request): Response
    {
        return $this->handle($request, fn(StatsPeriod $period, \DateTimeImmutable $now): array => $this->dashboard->getOverview($period, $now));
    }

    #[Route(path: '/terms', name: 'terms', methods: ['GET'])]
    public function terms(Request $request): Response
    {
        return $this->handle($request, fn(StatsPeriod $period): array => $this->dashboard->getTerms(
            $period,
            $this->string($request, 'q'),
            $this->string($request, 'sort'),
            $this->string($request, 'dir'),
            (int)$request->query->get('page', 1),
            (int)$request->query->get('perPage', 25),
        ));
    }

    #[Route(path: '/term', name: 'term', methods: ['GET'])]
    public function term(Request $request): Response
    {
        $keyword = trim($this->string($request, 'keyword'));

        if ('' === $keyword || mb_strlen($keyword) > 255) {
            return $this->error('Missing or invalid search term.', 400);
        }

        return $this->handle($request, fn(StatsPeriod $period, \DateTimeImmutable $now): array => $this->dashboard->getTerm($period, $keyword, $now));
    }

    #[Route(path: '/export', name: 'export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        return $this->handle($request, function (StatsPeriod $period) use ($request): Response {
            System::loadLanguageFile('tl_search_stats_log');

            return $this->exporter->createResponse(
                $this->string($request, 'mode'),
                $period->getStartTimestamp(),
                $period->getEndTimestamp(),
                (array)($GLOBALS['TL_LANG']['tl_search_stats_log']['csv'] ?? []),
            );
        });
    }

    private function handle(Request $request, callable $build): Response
    {
        $this->initializeContaoFramework();

        if (!$this->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, self::MODULE)) {
            return $this->error('Access denied.', 403);
        }

        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->save();
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));

        try {
            $period = StatsPeriod::create($this->string($request, 'period') ?: '30d', $this->string($request, 'from'), $this->string($request, 'to'), $now);
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 400);
        }

        $result = $build($period, $now);

        if ($result instanceof Response) {
            return $result;
        }

        $response = $this->json($result);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function error(string $message, int $status): JsonResponse
    {
        $response = $this->json(['error' => $message], $status);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function string(Request $request, string $key): string
    {
        $value = $request->query->all()[$key] ?? '';

        return \is_string($value) ? $value : '';
    }
}