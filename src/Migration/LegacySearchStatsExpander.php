<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Migration;

use Alnv\ProSearchIndexerContaoAdapterBundle\Stats\SearchStatsLogger;

final class LegacySearchStatsExpander
{
    /**
     * @param array<string, mixed> $row row of tl_search_stats
     *
     * @return \Generator<int, array{tstamp: int, keyword: string, hits: int, clicks: int, urls: string|null, types: string, source: string, legacy: string}>
     */
    public static function expand(array $row): \Generator
    {
        $keyword = mb_substr(trim((string)($row['keywords'] ?? '')), 0, 255);
        $count = max(0, (int)($row['count'] ?? 0));

        if ('' === $keyword || 0 === $count) {
            return;
        }

        $tstamp = max(0, (int)($row['tstamp'] ?? 0));
        $hits = max(0, (int)($row['hits'] ?? 0));
        $clicks = max(0, (int)($row['clicks'] ?? 0));
        $baseHits = intdiv($hits, $count);
        $extraHits = $hits % $count;
        $types = SearchStatsLogger::normalizeTypes(self::unserializeList($row['types'] ?? null));
        $urls = array_slice(SearchStatsLogger::unserializeUrls($row['urls'] ?? null), 0, SearchStatsLogger::MAX_URLS);
        $sources = self::expandSources($row['source'] ?? null, $count);

        for ($i = 0; $i < $count; ++$i) {
            yield [
                'tstamp' => $tstamp,
                'keyword' => $keyword,
                'hits' => $baseHits + ($i < $extraHits ? 1 : 0),
                'clicks' => 0 === $i ? $clicks : 0,
                'urls' => 0 === $i && [] !== $urls ? serialize($urls) : null,
                'types' => $types,
                'source' => $sources[$i] ?? '',
                'legacy' => '1',
            ];
        }
    }

    /**
     * @return array{searches: int, hits: int, clicks: int} what expand() produces for the row (for the verification)
     */
    public static function totals(array $row): array
    {
        $count = max(0, (int)($row['count'] ?? 0));

        if ('' === trim((string)($row['keywords'] ?? '')) || 0 === $count) {
            return ['searches' => 0, 'hits' => 0, 'clicks' => 0];
        }

        return ['searches' => $count, 'hits' => max(0, (int)($row['hits'] ?? 0)), 'clicks' => max(0, (int)($row['clicks'] ?? 0))];
    }

    /**
     * Source per row index; the old format is a list of ['source' => URL, 'click' => number of searches].
     *
     * @return array<int, string>
     */
    private static function expandSources(mixed $value, int $count): array
    {
        $result = [];

        foreach (self::unserializeList($value) as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $source = SearchStatsLogger::normalizeSource((string)($entry['source'] ?? ''));
            $number = max(0, (int)($entry['click'] ?? 0));

            for ($i = 0; $i < $number && \count($result) < $count; ++$i) {
                $result[] = $source;
            }
        }

        return $result;
    }

    /**
     * @return array<mixed>
     */
    private static function unserializeList(mixed $value): array
    {
        if (\is_array($value)) {
            return $value;
        }

        if (!\is_string($value) || '' === $value) {
            return [];
        }

        $list = @unserialize($value, ['allowed_classes' => false]);

        return \is_array($list) ? $list : [];
    }
}
