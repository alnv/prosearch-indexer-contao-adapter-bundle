<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Stats;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class SearchStatsLogger
{
    public const MAX_URLS = 50;

    private bool $failed = false;

    public function __construct(
        private readonly Connection       $connection,
        private readonly int              $retentionDays = 0,
        private readonly ?LoggerInterface $logger = null,
    )
    {
    }

    public function log(string $keyword, int $hits, array $types = [], string $source = '', ?int $time = null): ?int
    {
        $keyword = trim($keyword);

        if ('' === $keyword || $this->failed) {
            return null;
        }

        try {
            $this->connection->insert(SearchStatsRepository::LOG_TABLE, [
                'tstamp' => $time ?? time(),
                'keyword' => mb_substr($keyword, 0, 255),
                'hits' => max(0, $hits),
                'types' => self::normalizeTypes($types),
                'source' => self::normalizeSource($source),
            ]);

            return (int)$this->connection->lastInsertId();
        } catch (\Throwable $exception) {
            $this->failed = true;
            $this->logger?->error('ProSearch: search could not be written to the statistics log: ' . $exception->getMessage());
        }

        return null;
    }

    public function logClick(string $keyword, string $url): bool
    {
        $keyword = trim($keyword);

        if ('' === $keyword || $this->failed) {
            return false;
        }

        try {
            $row = $this->connection->fetchAssociative(
                'SELECT id, urls FROM ' . SearchStatsRepository::LOG_TABLE . ' WHERE keyword = ? ORDER BY tstamp DESC, id DESC LIMIT 1',
                [mb_substr($keyword, 0, 255)],
            );

            if (false === $row) {
                return false;
            }

            $urls = self::unserializeUrls($row['urls'] ?? null);
            $url = mb_substr(trim($url), 0, 2048);

            if ('' !== $url && !\in_array($url, $urls, true) && \count($urls) < self::MAX_URLS) {
                $urls[] = $url;
            }

            $this->connection->executeStatement(
                'UPDATE ' . SearchStatsRepository::LOG_TABLE . ' SET clicks = clicks + 1, urls = ? WHERE id = ?',
                [[] === $urls ? null : serialize($urls), (int)$row['id']],
            );

            return true;
        } catch (\Throwable $exception) {
            $this->failed = true;
            $this->logger?->error('ProSearch: click could not be written to the statistics log: ' . $exception->getMessage());
        }

        return false;
    }

    public function purge(?int $now = null): int
    {
        if ($this->retentionDays <= 0) {
            return 0;
        }

        $limit = ($now ?? time()) - $this->retentionDays * 86400;
        $deleted = 0;

        do {
            $rows = (int)$this->connection->executeStatement('DELETE FROM ' . SearchStatsRepository::LOG_TABLE . ' WHERE tstamp < ? LIMIT 10000', [$limit]);
            $deleted += $rows;
        } while (10000 === $rows);

        return $deleted;
    }
    
    public function clear(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE ' . SearchStatsRepository::LOG_TABLE);
    }

    public function getRetentionDays(): int
    {
        return $this->retentionDays;
    }

    public static function normalizeTypes(array $types): string
    {
        $types = array_map(static fn($type): string => \is_scalar($type) ? trim((string)$type) : '', $types);
        $types = array_values(array_unique(array_filter($types, static fn(string $type): bool => '' !== $type)));
        sort($types, SORT_STRING);

        return mb_substr(implode(',', $types), 0, 255);
    }

    public static function normalizeSource(string $source): string
    {
        $source = trim(explode('?', $source, 2)[0]);

        return mb_substr($source, 0, 1024);
    }

    public static function unserializeUrls(mixed $value): array
    {
        if (!\is_string($value) || '' === $value) {
            return [];
        }

        $urls = @unserialize($value, ['allowed_classes' => false]);

        return \is_array($urls) ? array_values(array_filter($urls, 'is_string')) : [];
    }
}
