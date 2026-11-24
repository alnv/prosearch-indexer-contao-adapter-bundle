<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Stats;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class SearchStatsRepository
{
    public const LOG_TABLE = 'tl_search_stats_log';

    public const SORT_SEARCHES = 'searches';
    public const SORT_LAST = 'last';
    public const SORT_FIRST = 'first';
    public const SORT_KEYWORD = 'keyword';
    public const SORT_AVG_HITS = 'avg_hits';
    public const SORT_CLICKS = 'clicks';
    public const SORTS = [self::SORT_SEARCHES, self::SORT_LAST, self::SORT_FIRST, self::SORT_KEYWORD, self::SORT_AVG_HITS, self::SORT_CLICKS];

    private const EXPORT_BATCH = 2000;

    private const TERM_INDEX_THRESHOLD = 20000;
    private const TERM_INDEX = 'keyword_tstamp_hits_clicks';

    private array $rangeSizes = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getLogStart(): ?int
    {
        $value = $this->connection->fetchOne('SELECT MIN(tstamp) FROM ' . self::LOG_TABLE);

        return null === $value || false === $value ? null : (int)$value;
    }

    public function countSearches(?int $start, int $end): int
    {
        [$where, $params] = $this->range($start, $end);

        return (int)$this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::LOG_TABLE . ' WHERE ' . $where, $params);
    }

    public function getPeriodTotals(?int $start, int $end): array
    {
        [$where, $params] = $this->range($start, $end);

        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS searches, COALESCE(SUM(hits = 0), 0) AS zero, COALESCE(AVG(hits), 0) AS avg_hits, COALESCE(SUM(clicks), 0) AS clicks, COALESCE(SUM(legacy = \'1\'), 0) AS legacy FROM ' . self::LOG_TABLE . ' WHERE ' . $where,
            $params,
        ) ?: [];

        // Same range condition as countSearches(): reuse the number for the index decision of countTerms()
        $this->rangeSizes[($start ?? '-') . ':' . $end] = (int)($row['searches'] ?? 0);

        return [
            'searches' => (int)($row['searches'] ?? 0),
            'keywords' => $this->countTerms($start, $end),
            'zero' => (int)($row['zero'] ?? 0),
            'avg_hits' => round((float)($row['avg_hits'] ?? 0), 1),
            'clicks' => (int)($row['clicks'] ?? 0),
            'legacy' => (int)($row['legacy'] ?? 0),
        ];
    }

    public function getHourlyBuckets(?int $start, int $end, ?string $keyword = null): array
    {
        [$where, $params] = $this->range($start, $end);

        if (null !== $keyword) {
            $where .= ' AND keyword = ?';
            $params[] = $keyword;
        }

        $rows = $this->connection->fetchAllKeyValue(
            'SELECT FLOOR(tstamp / 3600) AS hour_index, COUNT(*) AS searches FROM ' . self::LOG_TABLE . ' WHERE ' . $where . ' GROUP BY hour_index',
            $params,
        );

        $buckets = [];

        foreach ($rows as $hour => $count) {
            $buckets[(int)$hour] = (int)$count;
        }

        return $buckets;
    }

    public function getTopTerms(?int $start, int $end, int $limit, int $offset = 0, bool $zeroHitsOnly = false): array
    {
        [$where, $params] = $this->range($start, $end);

        if ($zeroHitsOnly) {
            $where .= ' AND hits = 0';
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT keyword, COUNT(*) AS searches, AVG(hits) AS avg_hits, SUM(hits = 0) AS zero, SUM(clicks) AS clicks FROM ' . self::LOG_TABLE . $this->termIndexHint($start, $end) . ' WHERE ' . $where
            . ' GROUP BY keyword ORDER BY searches DESC, keyword ASC LIMIT ' . $this->int($limit, 1, 500) . ' OFFSET ' . $this->int($offset, 0, PHP_INT_MAX),
            $params,
        );

        return array_map(static fn(array $row): array => [
            'keyword' => (string)$row['keyword'],
            'searches' => (int)$row['searches'],
            'avg_hits' => round((float)$row['avg_hits'], 1),
            'zero' => (int)$row['zero'],
            'clicks' => (int)$row['clicks'],
        ], $rows);
    }

    public function countTerms(?int $start, int $end, string $query = ''): int
    {
        [$where, $params] = $this->range($start, $end);
        [$where, $params] = $this->search($where, $params, $query);

        return (int)$this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT keyword FROM ' . self::LOG_TABLE . $this->termIndexHint($start, $end) . ' WHERE ' . $where . ' GROUP BY keyword) t', $params);
    }

    public function getTrendingTerms(int $start, int $end, int $previousStart, int $previousEnd, string $direction, int $limit, int $minVolume): array
    {
        $where = 'rising' === $direction ? 't.current >= ? AND t.current > t.previous' : 't.previous >= ? AND t.previous > t.current';
        $order = 'rising' === $direction ? '(t.current - t.previous) DESC, t.current DESC' : '(t.previous - t.current) DESC, t.previous DESC';

        // Derived table: MySQL does not allow expressions over aggregate aliases in ORDER BY
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.keyword, t.current, t.previous FROM (SELECT keyword, SUM(tstamp >= ?) AS current, SUM(tstamp < ?) AS previous FROM ' . self::LOG_TABLE . $this->termIndexHint($previousStart, $end)
            . ' WHERE keyword != \'\' AND ((tstamp >= ? AND tstamp < ?) OR (tstamp >= ? AND tstamp < ?)) GROUP BY keyword) t'
            . ' WHERE ' . $where . ' ORDER BY ' . $order . ', t.keyword ASC LIMIT ' . $this->int($limit, 1, 100),
            [$start, $start, $previousStart, $previousEnd, $start, $end, $minVolume],
        );

        return array_map(static fn(array $row): array => [
            'keyword' => (string)$row['keyword'],
            'current' => (int)$row['current'],
            'previous' => (int)$row['previous'],
        ], $rows);
    }

    public function getEmergingTerms(int $start, int $end, int $limit, int $minVolume): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.keyword, t.current FROM (SELECT keyword, COUNT(*) AS current FROM ' . self::LOG_TABLE . $this->termIndexHint($start, $end)
            . ' WHERE keyword != \'\' AND tstamp >= ? AND tstamp < ? GROUP BY keyword HAVING current >= ?) t'
            . ' WHERE NOT EXISTS (SELECT 1 FROM ' . self::LOG_TABLE . ' l WHERE l.keyword = t.keyword AND l.tstamp < ?)'
            . ' ORDER BY t.current DESC, t.keyword ASC LIMIT ' . $this->int($limit, 1, 100),
            [$start, $end, $minVolume, $start],
        );

        return array_map(static fn(array $row): array => [
            'keyword' => (string)$row['keyword'],
            'current' => (int)$row['current'],
        ], $rows);
    }

    public function getTermRows(?int $start, int $end, string $query, string $sort, string $direction, int $limit, int $offset): array
    {
        [$where, $params] = $this->range($start, $end);
        [$where, $params] = $this->search($where, $params, $query);

        $order = match ($sort) {
            self::SORT_LAST => 'last_search',
            self::SORT_FIRST => 'first_search',
            self::SORT_KEYWORD => 'keyword',
            self::SORT_AVG_HITS => 'avg_hits',
            self::SORT_CLICKS => 'clicks',
            default => 'searches',
        };

        $rows = $this->connection->fetchAllAssociative(
            'SELECT keyword, COUNT(*) AS searches, AVG(hits) AS avg_hits, SUM(hits = 0) AS zero, SUM(clicks) AS clicks, MIN(tstamp) AS first_search, MAX(tstamp) AS last_search FROM ' . self::LOG_TABLE . $this->termIndexHint($start, $end)
            . ' WHERE ' . $where . ' GROUP BY keyword ORDER BY ' . $order . ' ' . $this->direction($direction) . ', keyword ASC LIMIT ' . $this->int($limit, 1, 500) . ' OFFSET ' . $this->int($offset, 0, PHP_INT_MAX),
            $params,
        );

        return array_map($this->mapTermRow(...), $rows);
    }

    public function getFirstSeen(array $keywords): array
    {
        if ([] === $keywords) {
            return [];
        }

        $rows = $this->connection->fetchAllKeyValue(
            'SELECT keyword, MIN(tstamp) FROM ' . self::LOG_TABLE . ' WHERE keyword IN (?) GROUP BY keyword',
            [array_values($keywords)],
            [ArrayParameterType::STRING],
        );

        $result = [];

        foreach ($rows as $keyword => $tstamp) {
            $result[mb_strtolower((string)$keyword)] = (int)$tstamp;
        }

        return $result;
    }

    public function getAllTimeTotals(): array
    {
        $totals = $this->getPeriodTotals(null, PHP_INT_MAX);

        return ['searches' => $totals['searches'], 'keywords' => $totals['keywords'], 'clicks' => $totals['clicks'], 'legacy' => $totals['legacy']];
    }

    public function getTerm(string $keyword, ?int $start, int $end): array
    {
        [$where, $params] = $this->range($start, $end);

        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS searches, AVG(hits) AS avg_hits, SUM(hits = 0) AS zero, SUM(clicks) AS clicks, MIN(tstamp) AS first_search, MAX(tstamp) AS last_search FROM ' . self::LOG_TABLE . ' WHERE ' . $where . ' AND keyword = ?',
            [...$params, $keyword],
        ) ?: [];

        return [
            'searches' => (int)($row['searches'] ?? 0),
            'avg_hits' => round((float)($row['avg_hits'] ?? 0), 1),
            'zero' => (int)($row['zero'] ?? 0),
            'clicks' => (int)($row['clicks'] ?? 0),
            'first' => isset($row['first_search']) ? (int)$row['first_search'] : null,
            'last' => isset($row['last_search']) ? (int)$row['last_search'] : null,
        ];
    }

    public function iterateLog(?int $start, int $end): \Generator
    {
        [$where, $params] = $this->range($start, $end);
        $cursor = null;

        do {
            $sql = 'SELECT id, tstamp, keyword, hits, clicks, urls, types, source, legacy FROM ' . self::LOG_TABLE . ' WHERE ' . $where;
            $batchParams = $params;

            if (null !== $cursor) {
                // Keyset pagination on (tstamp, id) instead of OFFSET
                $sql .= ' AND (tstamp < ? OR (tstamp = ? AND id < ?))';
                array_push($batchParams, $cursor[0], $cursor[0], $cursor[1]);
            }

            $rows = $this->connection->fetchAllAssociative($sql . ' ORDER BY tstamp DESC, id DESC LIMIT ' . self::EXPORT_BATCH, $batchParams);

            foreach ($rows as $row) {
                $cursor = [(int)$row['tstamp'], (int)$row['id']];
                yield $row;
            }
        } while (\count($rows) === self::EXPORT_BATCH);
    }

    public function iterateTerms(?int $start, int $end): \Generator
    {
        [$where, $params] = $this->range($start, $end);
        $cursor = null;

        do {
            $batchWhere = $where;
            $batchParams = $params;

            if (null !== $cursor) {
                $batchWhere .= ' AND keyword > ?';
                $batchParams[] = $cursor;
            }

            $rows = $this->connection->fetchAllAssociative(
                'SELECT keyword, COUNT(*) AS searches, AVG(hits) AS avg_hits, SUM(hits = 0) AS zero, SUM(clicks) AS clicks, MIN(tstamp) AS first_search, MAX(tstamp) AS last_search FROM ' . self::LOG_TABLE . ' FORCE INDEX (' . self::TERM_INDEX . ')'
                . ' WHERE ' . $batchWhere . ' GROUP BY keyword ORDER BY keyword ASC LIMIT ' . self::EXPORT_BATCH,
                $batchParams,
            );

            foreach ($rows as $row) {
                $cursor = (string)$row['keyword'];
                yield $this->mapTermRow($row);
            }
        } while (\count($rows) === self::EXPORT_BATCH);
    }

    private function mapTermRow(array $row): array
    {
        return [
            'keyword' => (string)$row['keyword'],
            'searches' => (int)$row['searches'],
            'avg_hits' => round((float)$row['avg_hits'], 1),
            'zero' => (int)$row['zero'],
            'clicks' => (int)$row['clicks'],
            'first' => (int)$row['first_search'],
            'last' => (int)$row['last_search'],
        ];
    }

    private function termIndexHint(?int $start, int $end): string
    {
        // No lower bound = the entire statistics, the term index is always the better choice
        if (null === $start) {
            return ' FORCE INDEX (' . self::TERM_INDEX . ')';
        }

        $key = $start . ':' . $end;
        $this->rangeSizes[$key] ??= $this->countSearches($start, $end);

        return $this->rangeSizes[$key] > self::TERM_INDEX_THRESHOLD ? ' FORCE INDEX (' . self::TERM_INDEX . ')' : '';
    }

    private function range(?int $start, int $end): array
    {
        if (null === $start) {
            return ['keyword != \'\' AND tstamp < ?', [$end]];
        }

        return ['keyword != \'\' AND tstamp >= ? AND tstamp < ?', [$start, $end]];
    }

    private function search(string $where, array $params, string $query): array
    {
        $query = trim($query);

        if ('' === $query) {
            return [$where, $params];
        }

        $params[] = '%' . addcslashes($query, '%_\\') . '%';

        return [$where . ' AND keyword LIKE ?', $params];
    }

    private function direction(string $direction): string
    {
        return 'asc' === strtolower($direction) ? 'ASC' : 'DESC';
    }

    private function int(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}