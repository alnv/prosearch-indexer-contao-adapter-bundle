<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Logger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Contao\System;
use Psr\Log\LogLevel;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SearchService
{
    public const string MODE_SEARCH = 'search';
    public const string MODE_AUTOCOMPLETE = 'autocomplete';

    public const int CAPABILITIES_TTL = 3600;
    public const int CAPABILITIES_ERROR_TTL = 300;

    private array $synonymSupport = [];

    public function __construct(
        private readonly ProxyClient     $proxy,
        private readonly LicenseProvider $licenseProvider,
        private readonly string          $profile = 'prosearch_default',
        private readonly ?CacheInterface $cache = null,
    )
    {
    }

    public function supportsSynonyms(): bool
    {
        $key = $this->proxy->getBaseUrl() . '|' . $this->profile;

        if (isset($this->synonymSupport[$key])) {
            return $this->synonymSupport[$key];
        }

        $detect = function (?ItemInterface $item = null): bool {
            try {
                $params = (array)($this->proxy->capabilities()['profile_params'][$this->profile] ?? []);

                return \in_array('synonyms', $params, true);
            } catch (ProxyException $e) {
                $item?->expiresAfter(self::CAPABILITIES_ERROR_TTL);
                Logger::set('ProSearch: proxy capabilities not available, synonyms replace the query: ' . $e->getMessage(), LogLevel::WARNING, __METHOD__);

                return false;
            }
        };

        if (null === $this->cache) {
            return $this->synonymSupport[$key] = $detect();
        }

        return $this->synonymSupport[$key] = $this->cache->get('prosearch_synonyms_' . md5($key), static function (ItemInterface $item) use ($detect): bool {
            $item->expiresAfter(self::CAPABILITIES_TTL);

            return $detect($item);
        });
    }

    public function search(array $keywords, array $options, string $index): array
    {
        $request = $this->createRequest($keywords, $options, self::MODE_SEARCH);

        try {
            $result = $this->proxy->search($this->licenseProvider->getLicenseIdentifier(), $index, $request);
        } catch (ProxyException $e) {
            Logger::set('ProSearch search failed: ' . $e->getMessage(), LogLevel::ERROR, __METHOD__);

            return ['hits' => [], 'didYouMean' => [], 'autocomplete' => [], 'max_score' => 0, 'error' => $e->getErrorCode()];
        }

        $suggestions = (array)($result['suggestions'] ?? []);

        return [
            'tries' => (int)($result['step'] ?? 0),
            'hits' => array_map(static fn(array $hit): array => [
                '_index' => $hit['index'] ?? $index,
                '_id' => $hit['id'] ?? '',
                '_score' => $hit['score'] ?? 0,
                '_source' => (array)($hit['source'] ?? []),
                'highlight' => (array)($hit['highlight'] ?? []),
                'sort' => $hit['sort'] ?? [],
            ], $result['hits'] ?? []),
            'max_score' => $result['max_score'] ?? 0,
            'total' => (int)($result['total'] ?? 0),
            'didYouMean' => array_values((array)($suggestions['did_you_mean'] ?? [])),
            'autocomplete' => $this->mapAutocomplete($suggestions['autocomplete'] ?? []),
        ];
    }

    public function autocomplete(array $keywords, array $options, string $index): array
    {
        $request = $this->createRequest($keywords, $options, self::MODE_AUTOCOMPLETE);

        try {
            $result = $this->proxy->searchStrategy($this->licenseProvider->getLicenseIdentifier(), $index, 'autocomplete', $request);
        } catch (ProxyException $e) {
            Logger::set('ProSearch auto completion failed: ' . $e->getMessage(), LogLevel::ERROR, __METHOD__);

            return ['hits' => [], 'didYouMean' => []];
        }

        $suggestions = (array)($result['suggestions'] ?? []);

        return [
            'hits' => $this->mapAutocomplete($suggestions['autocomplete'] ?? []),
            'didYouMean' => array_values((array)($suggestions['did_you_mean'] ?? [])),
        ];
    }

    public function createRequest(array $keywords, array $options, string $mode): array
    {
        $query = (string)($keywords['query'] ?? '');
        $synonyms = array_values(array_filter((array)($keywords['synonyms'] ?? []), static fn($s): bool => \is_string($s) && '' !== $s));

        // proxy without synonym support: replace the query (ProSearch < 4)
        if ([] !== $synonyms && !$this->supportsSynonyms()) {
            $query = (string)($keywords['synonym'] ?? $synonyms[0]);
            $synonyms = [];
        }

        $request = [
            'query' => $query,
            'profile' => $this->profile,
            'params' => [
                'analyzer' => (string)(($options['analyzer'] ?? '') ?: 'standard'),
                'fuzzy' => true === ($options['fuzzy'] ?? false),
            ],
            'filters' => [],
        ];

        // only for the search: suggestions (auto completion, did-you-mean) refer to the typed text
        if ([] !== $synonyms && self::MODE_SEARCH === $mode) {
            $request['params']['synonyms'] = \array_slice($synonyms, 0, 10);
        }

        if (!empty($options['language'])) {
            $request['filters']['language'] = (string)$options['language'];
        }

        if (!empty($options['domain'])) {
            $request['filters']['domain'] = array_values(array_filter(explode(',', str_replace(' ', '', (string)$options['domain']))));
        }

        if (!empty($keywords['types']) && \is_array($keywords['types'])) {
            // keyword field (index from 4.0) or "types.keyword" (dynamic text mapping of older indices)
            $typesField = (string)($options['typesField'] ?? 'types');
            $request['filters'][preg_match('/^types(\.keyword)?$/', $typesField) ? $typesField : 'types'] = array_values(array_map('strtolower', array_filter($keywords['types'], 'is_string')));
        }

        if (self::MODE_SEARCH === $mode) {
            $request['size'] = min((int)(($options['perPage'] ?? 0) ?: 500), 1000);

            if (!empty($options['search_after'])) {
                $request['search_after'] = explode(',', (string)$options['search_after']);
            }
        }

        if ([] === $request['filters']) {
            unset($request['filters']);
        }

        $this->triggerHooks($request, $keywords, $options, $mode);

        return $request;
    }

    private function triggerHooks(array &$request, array $keywords, array $options, string $mode): void
    {
        foreach (['psSearchQuery', 'psAutoCompilation'] as $legacyHook) {
            if (!empty($GLOBALS['TL_HOOKS'][$legacyHook])) {
                trigger_deprecation('alnv/prosearch-indexer-contao-adapter-bundle', '4.0', 'The "%s" hook is no longer called because queries are built by the ProSearch proxy. Use "psProxySearchRequest" or a SearchBodyEvent listener in the proxy.', $legacyHook);
            }
        }

        if (empty($GLOBALS['TL_HOOKS']['psProxySearchRequest']) || !\is_array($GLOBALS['TL_HOOKS']['psProxySearchRequest'])) {
            return;
        }

        foreach ($GLOBALS['TL_HOOKS']['psProxySearchRequest'] as $callback) {
            if (\is_array($callback) && isset($callback[0], $callback[1])) {
                System::importStatic($callback[0])->{$callback[1]}($request, $keywords, $options, $mode);
            }
        }
    }

    private function mapAutocomplete(iterable $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $text = (string)((array)$item)['text'];
            $result[] = ['term' => $text, 'template' => $text];
        }

        return $result;
    }
}
