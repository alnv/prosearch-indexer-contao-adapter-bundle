<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class IndexSchemaResolver
{
    public const string DEFAULT_ANALYZER = 'contao';
    public const int CACHE_TTL = 3600;
    public const int CACHE_ERROR_TTL = 60;

    private array $schemas = [];

    public function __construct(
        private readonly Connection       $connection,
        private readonly ProxyClient      $proxy,
        private readonly LicenseProvider  $licenseProvider,
        private readonly ?CacheInterface  $cache = null,
        private readonly ?LoggerInterface $logger = null,
    )
    {
    }

    public function getConfiguredAnalyzer(int|string|null $rootPageId): string
    {
        $credentials = $this->licenseProvider->getCredentials() ?: [];

        if (!empty($credentials['singleDocument'])) {
            return self::sanitize((string)($credentials['analyzer'] ?? ''));
        }

        $analyzer = '';

        if ((int)$rootPageId > 0) {
            $analyzer = (string)$this->connection->fetchOne('SELECT psAnalyzer FROM tl_page WHERE id=?', [(int)$rootPageId]);
        }

        return self::sanitize('' !== $analyzer ? $analyzer : (string)($credentials['analyzer'] ?? ''));
    }

    public function getSchema(string $index, int|string|null $rootPageId = null): array
    {
        if (isset($this->schemas[$index])) {
            return $this->schemas[$index];
        }

        $detect = function (?ItemInterface $item = null) use ($index, $rootPageId): array {
            try {
                $properties = (array)($this->proxy->getMapping($this->licenseProvider->getLicenseIdentifier(), $index)['properties'] ?? []);
            } catch (ProxyException $e) {
                $item?->expiresAfter(self::CACHE_ERROR_TTL);

                if (404 !== $e->getStatusCode()) {
                    $this->logger?->warning('ProSearch: mapping of index {index} not available ({message}), the configured analyzer is used.', ['index' => $index, 'message' => $e->getMessage()]);
                }

                return ['analyzer' => $this->getConfiguredAnalyzer($rootPageId), 'typesField' => 'types'];
            }

            return self::fromProperties($properties, $this->getConfiguredAnalyzer($rootPageId));
        };

        if (null === $this->cache) {
            return $this->schemas[$index] = $detect();
        }

        return $this->schemas[$index] = $this->cache->get(self::cacheKey($index), static function (ItemInterface $item) use ($detect): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $detect($item);
        });
    }

    public function forget(string $index): void
    {
        unset($this->schemas[$index]);
        $this->cache?->delete(self::cacheKey($index));
    }

    public static function fromProperties(array $properties, string $fallbackAnalyzer = self::DEFAULT_ANALYZER): array
    {
        $text = (array)($properties['text'] ?? []);
        $analyzer = (string)($text['search_analyzer'] ?? $text['analyzer'] ?? '');

        if ('' === $analyzer) {
            // field without explicit analyzer: OpenSearch/Elasticsearch use "standard"
            $analyzer = isset($properties['text']) ? 'standard' : $fallbackAnalyzer;
        }

        $types = (array)($properties['types'] ?? []);
        $typesField = 'types';

        if ('text' === ($types['type'] ?? null) && 'keyword' === ($types['fields']['keyword']['type'] ?? null)) {
            $typesField = 'types.keyword';
        }

        return ['analyzer' => self::sanitize($analyzer), 'typesField' => $typesField];
    }

    public static function sanitize(string $analyzer): string
    {
        $analyzer = trim($analyzer);

        return preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $analyzer) ? $analyzer : self::DEFAULT_ANALYZER;
    }

    private static function cacheKey(string $index): string
    {
        return 'prosearch_schema_' . md5($index);
    }
}