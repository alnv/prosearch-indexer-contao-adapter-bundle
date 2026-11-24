<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Stats;

use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchDashboard;
use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsLogger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\StatsPeriod;
use Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\DatabaseTestCase;

/**
 * Dashboard SQL against MySQL/MariaDB (see DatabaseTestCase).
 */
final class SearchDashboardTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement(self::LOG_TABLE_SQL);

        $this->insertLog('alt', '2026-11-01 12:00', 3);          // first search
        $this->insertLog('termin', '2026-11-12 10:00', 7, 5);    // previous period
        $this->insertLog('kfz', '2026-11-16 09:00', 5);          // previous period
        $this->insertLog('termin', '2026-11-20 10:00', 7);       // Friday
        $this->insertLog('Führerschein', '2026-11-21 15:00', 9); // Saturday
        $this->insertLog('führerschein', '2026-11-21 15:20', 9);
        $this->insertLog('kfz', '2026-11-22 10:00', 5, 2);       // Sunday
        $this->insertLog('kfz', '2026-11-23 09:00', 5, 1, ['clicks' => 2]); // Monday (today), 2 clicks
        $this->insertLog('kfz', '2026-11-23 09:10', 5, 2);
        $this->insertLog('wohngeld', '2026-11-23 10:00', 0, 4);  // no results
        $this->insertLog('', '2026-11-23 10:30', 1);             // empty term → ignored
    }

    public function testOverviewForTheLastSevenDays(): void
    {
        $now = $this->at('2026-11-23 11:30');
        $overview = $this->dashboard()->getOverview(StatsPeriod::create('7d', null, null, $now), $now);

        $this->assertTrue($overview['log']['hasPrevious']);
        $this->assertSame($this->at('2026-11-01 12:00')->getTimestamp(), $overview['log']['start']);
        $this->assertSame(0, $overview['log']['legacy']);

        // Period [17.11. 00:00, 23.11. 11:30): kfz 5, wohngeld 4, Führerschein 2, termin 1
        $this->assertSame(['value' => 12, 'previous' => 6, 'change' => 100.0], $overview['kpis']['searches']);
        $this->assertSame(4, $overview['kpis']['keywords']['value'], 'Führerschein/führerschein is one term, the empty term is ignored');
        $this->assertSame('kfz', $overview['kpis']['top']['keyword']);
        $this->assertSame(41.7, $overview['kpis']['top']['share']);
        $this->assertSame(33.3, $overview['kpis']['zeroRate']['value']);
        $this->assertSame(['value' => 16.7, 'previous' => 0.0, 'change' => 16.7, 'clicks' => 2], $overview['kpis']['clickRate']);
        $this->assertSame(7, $overview['kpis']['avgPerDay']['days']);
        $this->assertSame(1.7, $overview['kpis']['avgPerDay']['value']);

        // Calendar: today until 11:30 (kfz 3 + wohngeld 4) vs. yesterday until 11:30 (kfz 2)
        $this->assertSame(7, $overview['calendar']['today']['value']);
        $this->assertSame(2, $overview['calendar']['today']['previous']);
        $this->assertSame(250.0, $overview['calendar']['today']['change']);

        $this->assertSame([0, 0, 0, 1, 2, 2, 7], array_column($overview['timeline']['current'], 'value'));
        $this->assertSame([7, 0, 0, 0, 1, 2, 2], $overview['weekdays'], 'Mon … Sun');
        $this->assertSame(3, $overview['hours'][9]);
        $this->assertSame(['kfz', 'wohngeld'], array_column($overview['trends']['rising'], 'keyword'));
        $this->assertSame([['keyword' => 'termin', 'current' => 1, 'previous' => 5]], $overview['trends']['falling']);
        $this->assertSame([['keyword' => 'wohngeld', 'current' => 4]], $overview['trends']['emerging']);
        $this->assertSame([['keyword' => 'wohngeld', 'searches' => 4, 'avg_hits' => 0.0, 'zero' => 4, 'clicks' => 0]], $overview['zeroTerms']);
        $this->assertSame(['searches' => 19, 'keywords' => 5, 'clicks' => 2, 'legacy' => 0], $overview['allTime']);
    }

    public function testAllTime(): void
    {
        $now = $this->at('2026-11-23 11:30');
        $overview = $this->dashboard()->getOverview(StatsPeriod::create('all', null, null, $now), $now);

        $this->assertSame(['value' => 19, 'previous' => null, 'change' => null], $overview['kpis']['searches']);
        $this->assertSame(['kfz', 'termin', 'wohngeld', 'Führerschein', 'alt'], array_column($overview['topTerms']['items'], 'keyword'));
        $this->assertFalse($overview['trends']['available']);
        $this->assertSame('2026-11-01', $overview['timeline']['current'][0]['key'], 'timeline starts with the first search');
        $this->assertTrue($overview['log']['coversPeriod']);
    }

    public function testPeriodBeforeTheFirstSearchHasNoComparisonAndUnknownDays(): void
    {
        $now = $this->at('2026-11-05 12:00');
        $overview = $this->dashboard()->getOverview(StatsPeriod::create('7d', null, null, $now), $now);

        $this->assertFalse($overview['log']['hasPrevious']);
        $this->assertFalse($overview['log']['coversPeriod']);
        $this->assertNull($overview['kpis']['searches']['previous']);
        $this->assertSame(['available' => false, 'reason' => 'log_too_short'], $overview['trends']);
        $this->assertSame([null, null, 1, 0, 0, 0, 0], array_column($overview['timeline']['current'], 'value'), 'days before 01.11. are unknown, not 0');
        $this->assertSame(5, $overview['kpis']['avgPerDay']['days']);
    }

    public function testLegacyRowsAreReported(): void
    {
        $this->insertLog('altdaten', '2026-11-20 08:00', 2, 3, ['legacy' => '1']);
        $now = $this->at('2026-11-23 11:30');

        $this->assertSame(3, $this->dashboard()->getOverview(StatsPeriod::create('7d', null, null, $now), $now)['log']['legacy']);
        $this->assertSame(0, $this->dashboard()->getOverview(StatsPeriod::create('today', null, null, $now), $now)['log']['legacy']);
    }

    public function testTermsTable(): void
    {
        $now = $this->at('2026-11-23 11:30');
        $dashboard = $this->dashboard();
        $period = StatsPeriod::create('7d', null, null, $now);

        $page = $dashboard->getTerms($period, '', 'searches', 'desc', 1, 25);
        $this->assertSame(4, $page['total']);
        $this->assertSame(['kfz', 'wohngeld', 'Führerschein', 'termin'], array_column($page['rows'], 'keyword'));
        $this->assertSame(['keyword' => 'kfz', 'searches' => 5, 'avg_hits' => 5.0, 'zero' => 0, 'clicks' => 2, 'first' => $this->at('2026-11-22 10:00')->getTimestamp(), 'last' => $this->at('2026-11-23 09:10')->getTimestamp() + 1, 'firstSeen' => $this->at('2026-11-16 09:00')->getTimestamp()], $page['rows'][0]);

        $this->assertSame(['Führerschein'], array_column($dashboard->getTerms($period, 'FÜHR', 'searches', 'desc', 1, 25)['rows'], 'keyword'), 'case-insensitive filter');
        $this->assertSame(0, $dashboard->getTerms($period, '%', 'searches', 'desc', 1, 25)['total'], 'LIKE wildcards are escaped');
        $this->assertSame('kfz', $dashboard->getTerms($period, '', 'clicks', 'desc', 1, 25)['rows'][0]['keyword']);

        $sorted = $dashboard->getTerms($period, '', 'keyword', 'asc', 2, 10);
        $this->assertSame(1, $sorted['page'], 'page is clamped to the last page');
        $this->assertSame('Führerschein', $sorted['rows'][0]['keyword']);

        $injected = $dashboard->getTerms($period, '', 'searches; DROP TABLE tl_search_stats_log', 'sideways', 1, 7);
        $this->assertSame(['searches', 'desc', 25], [$injected['sort'], $injected['direction'], $injected['perPage']]);

        $all = $dashboard->getTerms(StatsPeriod::create('all', null, null, $now), '', 'first', 'desc', 1, 25);
        $this->assertSame(['wohngeld', 'Führerschein', 'kfz', 'termin', 'alt'], array_column($all['rows'], 'keyword'));
        $this->assertSame(array_column($all['rows'], 'first'), array_column($all['rows'], 'firstSeen'));
    }

    public function testTermDetail(): void
    {
        $now = $this->at('2026-11-23 11:30');
        $term = $this->dashboard()->getTerm(StatsPeriod::create('7d', null, null, $now), 'kfz', $now);

        $this->assertSame(5, $term['term']['searches']);
        $this->assertSame(2, $term['term']['clicks']);
        $this->assertSame(6, $term['allTime']['searches']);
        $this->assertSame($this->at('2026-11-16 09:00')->getTimestamp(), $term['allTime']['first']);
        $this->assertSame([0, 0, 0, 0, 0, 2, 3], array_column($term['timeline']['current'], 'value'));
    }

    private function dashboard(): SearchDashboard
    {
        return new SearchDashboard(new SearchStatsRepository($this->connection), new SearchStatsLogger($this->connection));
    }
}
