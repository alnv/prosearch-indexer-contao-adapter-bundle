<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Stats;

class SearchDashboard
{
    public const TOP_TERMS_LIMIT = 50;
    public const LIST_LIMIT = 10;
    public const TREND_MIN_VOLUME = 3;
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function __construct(private readonly SearchStatsRepository $repository, private readonly SearchStatsLogger $statsLogger)
    {
    }

    public function getOverview(StatsPeriod $period, \DateTimeImmutable $now): array
    {
        $logStart = $this->repository->getLogStart();
        $timezone = $now->getTimezone();
        $start = $period->getStartTimestamp();
        $end = $period->getEndTimestamp();
        $hasPrevious = $period->hasPrevious() && null !== $logStart && $period->previousStart->getTimestamp() >= $logStart;

        $totals = $this->repository->getPeriodTotals($start, $end);
        $previousTotals = $hasPrevious ? $this->repository->getPeriodTotals($period->previousStart->getTimestamp(), $period->previousEnd->getTimestamp()) : null;

        $days = $this->countDays($start, $end, $logStart, $timezone);
        $previousDays = $hasPrevious ? $this->countDays($period->previousStart->getTimestamp(), $period->previousEnd->getTimestamp(), $logStart, $timezone) : 0;
        $avgPerDay = $days > 0 ? round($totals['searches'] / $days, 1) : 0.0;
        $previousAvgPerDay = $hasPrevious && $previousDays > 0 ? round($previousTotals['searches'] / $previousDays, 1) : null;

        $topTerms = $this->repository->getTopTerms($start, $end, self::TOP_TERMS_LIMIT);
        $top = $topTerms[0] ?? null;

        $granularity = $period->getGranularity($logStart);
        $chartStart = $period->start ?? (null !== $logStart ? (new \DateTimeImmutable('@' . $logStart))->setTimezone($timezone) : $now->setTime(0, 0));
        $buckets = new HourlyBuckets($this->repository->getHourlyBuckets($start, $end), $timezone);
        $current = $this->maskBeforeLogStart($buckets->series($chartStart, $period->end, $granularity), $logStart, $timezone, $end);
        $previous = null;

        if ($hasPrevious) {
            $previousBuckets = new HourlyBuckets($this->repository->getHourlyBuckets($period->previousStart->getTimestamp(), $period->previousEnd->getTimestamp()), $timezone);
            $previous = $previousBuckets->series($period->previousStart, $period->previousEnd, $granularity);
        }

        return [
            'period' => $this->describePeriod($period, $granularity),
            'log' => [
                'start' => $logStart,
                'retentionDays' => $this->statsLogger->getRetentionDays(),
                'coversPeriod' => null !== $logStart && (null === $start || $start >= $logStart),
                'hasPrevious' => $hasPrevious,
                'legacy' => $totals['legacy'],
            ],
            'calendar' => $this->getCalendarCards($now, $logStart),
            'kpis' => [
                'searches' => $this->metric($totals['searches'], $previousTotals['searches'] ?? null),
                'keywords' => $this->metric($totals['keywords'], $previousTotals['keywords'] ?? null),
                'top' => [
                    'keyword' => $top['keyword'] ?? '',
                    'searches' => $top['searches'] ?? 0,
                    'share' => $top ? $this->rate($top['searches'], $totals['searches']) : null,
                ],
                'avgPerDay' => $this->metric($avgPerDay, $previousAvgPerDay) + ['days' => $days],
                'zeroRate' => $this->rateMetric($totals['zero'], $totals['searches'], $previousTotals ? [$previousTotals['zero'], $previousTotals['searches']] : null) + ['zero' => $totals['zero']],
                'clickRate' => $this->rateMetric($totals['clicks'], $totals['searches'], $previousTotals ? [$previousTotals['clicks'], $previousTotals['searches']] : null) + ['clicks' => $totals['clicks']],
                'avgHits' => $totals['avg_hits'],
            ],
            // "all" ends now, so its totals are the totals of the entire statistics (saves the second aggregation)
            'allTime' => null === $start ? ['searches' => $totals['searches'], 'keywords' => $totals['keywords'], 'clicks' => $totals['clicks'], 'legacy' => $totals['legacy']] : $this->repository->getAllTimeTotals(),
            'timeline' => [
                'granularity' => $granularity,
                'current' => $current,
                'previous' => $previous,
                'peak' => $this->peak($current),
                'trend' => $this->trend($current),
            ],
            'weekdays' => $buckets->weekdays(),
            'hours' => $buckets->hours(),
            'topTerms' => ['items' => $topTerms],
            'trends' => $this->getTrends($period, $hasPrevious),
            'zeroTerms' => $this->repository->getTopTerms($start, $end, self::LIST_LIMIT, 0, true),
        ];
    }

    public function getTerms(StatsPeriod $period, string $query, string $sort, string $direction, int $page, int $perPage): array
    {
        $perPage = \in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;
        $query = mb_substr(trim($query), 0, 100);
        $sort = \in_array($sort, SearchStatsRepository::SORTS, true) ? $sort : SearchStatsRepository::SORT_SEARCHES;
        $start = $period->getStartTimestamp();
        $end = $period->getEndTimestamp();

        $total = $this->repository->countTerms($start, $end, $query);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $rows = $this->repository->getTermRows($start, $end, $query, $sort, $direction, $perPage, ($page - 1) * $perPage);
        $firstSeen = null === $start ? [] : $this->repository->getFirstSeen(array_column($rows, 'keyword'));

        foreach ($rows as &$row) {
            // First search ever (differs from "first in period" for filtered periods)
            $row['firstSeen'] = $firstSeen[mb_strtolower($row['keyword'])] ?? $row['first'];
        }

        unset($row);

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => 'asc' === strtolower($direction) ? 'asc' : 'desc',
            'query' => $query,
        ];
    }

    public function getTerm(StatsPeriod $period, string $keyword, \DateTimeImmutable $now): array
    {
        $timezone = $now->getTimezone();
        $logStart = $this->repository->getLogStart();
        $granularity = $period->getGranularity($logStart);
        $chartStart = $period->start ?? (null !== $logStart ? (new \DateTimeImmutable('@' . $logStart))->setTimezone($timezone) : $now->setTime(0, 0));
        $buckets = new HourlyBuckets($this->repository->getHourlyBuckets($period->getStartTimestamp(), $period->getEndTimestamp(), $keyword), $timezone);

        return [
            'keyword' => $keyword,
            'period' => $this->describePeriod($period, $granularity),
            'term' => $this->repository->getTerm($keyword, $period->getStartTimestamp(), $period->getEndTimestamp()),
            'allTime' => $this->repository->getTerm($keyword, null, PHP_INT_MAX),
            'timeline' => [
                'granularity' => $granularity,
                'current' => $this->maskBeforeLogStart($buckets->series($chartStart, $period->end, $granularity), $logStart, $timezone, $period->getEndTimestamp()),
            ],
            'weekdays' => $buckets->weekdays(),
            'hours' => $buckets->hours(),
        ];
    }

    private function getCalendarCards(\DateTimeImmutable $now, ?int $logStart): array
    {
        $today = $now->setTime(0, 0);
        $monday = $today->modify('-' . ((int)$today->format('N') - 1) . ' days');
        $monthStart = $today->setDate((int)$today->format('Y'), (int)$today->format('n'), 1);
        $previousMonthStart = $monthStart->modify('-1 month');
        $previousMonthEnd = min($previousMonthStart->getTimestamp() + ($now->getTimestamp() - $monthStart->getTimestamp()), $monthStart->getTimestamp());

        $windows = [
            'today' => [$today->getTimestamp(), $now->getTimestamp(), $today->modify('-1 day')->getTimestamp(), $now->modify('-1 day')->getTimestamp()],
            'yesterday' => [$today->modify('-1 day')->getTimestamp(), $today->getTimestamp(), $today->modify('-2 days')->getTimestamp(), $today->modify('-1 day')->getTimestamp()],
            'week' => [$monday->getTimestamp(), $now->getTimestamp(), $monday->modify('-7 days')->getTimestamp(), $now->modify('-7 days')->getTimestamp()],
            'month' => [$monthStart->getTimestamp(), $now->getTimestamp(), $previousMonthStart->getTimestamp(), $previousMonthEnd],
        ];

        $cards = [];

        foreach ($windows as $key => [$start, $end, $previousStart, $previousEnd]) {
            $comparable = null !== $logStart && $previousStart >= $logStart;
            $cards[$key] = $this->metric(
                    $this->repository->countSearches($start, $end),
                    $comparable ? $this->repository->countSearches($previousStart, $previousEnd) : null,
                ) + ['coversWindow' => null !== $logStart && $start >= $logStart];
        }

        return $cards;
    }

    /**
     * @return array<string, mixed>
     */
    private function getTrends(StatsPeriod $period, bool $hasPrevious): array
    {
        if (!$period->hasPrevious()) {
            return ['available' => false, 'reason' => 'no_previous'];
        }

        if (!$hasPrevious) {
            return ['available' => false, 'reason' => 'log_too_short'];
        }

        $start = $period->getStartTimestamp();
        $end = $period->getEndTimestamp();
        $previousStart = $period->previousStart->getTimestamp();
        $previousEnd = $period->previousEnd->getTimestamp();

        return [
            'available' => true,
            'minVolume' => self::TREND_MIN_VOLUME,
            'rising' => $this->repository->getTrendingTerms($start, $end, $previousStart, $previousEnd, 'rising', self::LIST_LIMIT, self::TREND_MIN_VOLUME),
            'falling' => $this->repository->getTrendingTerms($start, $end, $previousStart, $previousEnd, 'falling', self::LIST_LIMIT, self::TREND_MIN_VOLUME),
            'emerging' => $this->repository->getEmergingTerms($start, $end, self::LIST_LIMIT, self::TREND_MIN_VOLUME),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describePeriod(StatsPeriod $period, string $granularity): array
    {
        return [
            'preset' => $period->preset,
            'start' => $period->start?->format(\DATE_ATOM),
            'end' => $period->end->format(\DATE_ATOM),
            'previousStart' => $period->previousStart?->format(\DATE_ATOM),
            'previousEnd' => $period->previousEnd?->format(\DATE_ATOM),
            'granularity' => $granularity,
            'timezone' => $period->end->getTimezone()->getName(),
        ];
    }

    private function countDays(?int $start, int $end, ?int $logStart, \DateTimeZone $timezone): int
    {
        if (null === $logStart || $end <= $logStart) {
            return 0;
        }

        $first = (new \DateTimeImmutable('@' . max($start ?? $logStart, $logStart)))->setTimezone($timezone)->setTime(0, 0);
        $last = (new \DateTimeImmutable('@' . ($end - 1)))->setTimezone($timezone)->setTime(0, 0);

        return max(1, (int)$first->diff($last)->days + 1);
    }

    private function maskBeforeLogStart(array $series, ?int $logStart, \DateTimeZone $timezone, int $end): array
    {
        if (null === $logStart || $end <= $logStart) {
            return array_map(static fn(array $point): array => ['key' => $point['key'], 'value' => null], $series);
        }

        $firstDay = (new \DateTimeImmutable('@' . $logStart))->setTimezone($timezone)->format('Y-m-d');

        foreach ($series as $index => $point) {
            // A day/week is unknown if it ends before the first logged day (it ends where the next bucket starts;
            // the last bucket ends with the period, which always lies after the log start)
            $next = $series[$index + 1]['key'] ?? null;

            if (null !== $next && $next <= $firstDay) {
                $series[$index]['value'] = null;
            }
        }

        return $series;
    }

    private function peak(array $series): ?array
    {
        $peak = null;

        foreach ($series as $point) {
            if (null !== $point['value'] && $point['value'] > 0 && (null === $peak || $point['value'] > $peak['value'])) {
                $peak = $point;
            }
        }

        return $peak;
    }

    private function trend(array $series): ?array
    {
        $values = array_values(array_filter(array_column($series, 'value'), static fn($value): bool => null !== $value));
        $count = \count($values);

        if ($count < 4) {
            return null;
        }

        $half = intdiv($count, 2);
        $first = array_sum(\array_slice($values, 0, $half));
        $second = array_sum(\array_slice($values, $count - $half));

        return ['change' => $first > 0 ? round(($second - $first) / $first * 100, 1) : null, 'points' => $count];
    }

    private function metric(int|float $value, int|float|null $previous): array
    {
        return [
            'value' => $value,
            'previous' => $previous,
            'change' => null !== $previous && $previous > 0 ? round(($value - $previous) / $previous * 100, 1) : null,
        ];
    }

    private function rateMetric(int $part, int $total, ?array $previous): array
    {
        $value = $this->rate($part, $total);
        $previousValue = null === $previous ? null : $this->rate($previous[0], $previous[1]);

        return [
            'value' => $value,
            'previous' => $previousValue,
            'change' => null !== $value && null !== $previousValue ? round($value - $previousValue, 1) : null,
        ];
    }

    private function rate(int $part, int $total): ?float
    {
        return $total > 0 ? round($part / $total * 100, 1) : null;
    }
}
