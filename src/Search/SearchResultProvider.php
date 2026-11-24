<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Alnv\ProSearchIndexerContaoAdapterBundle\Entity\Result;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Keyword;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Stats;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Toolkit;
use Contao\FrontendTemplate;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;

class SearchResultProvider
{
    public function __construct(
        private readonly SearchService     $searchService,
        private readonly IndexNameResolver $indexNameResolver,
        private readonly ?IndexSchemaResolver $schemaResolver = null,
    )
    {
    }

    public function search(array $params, ?object $context = null): array
    {
        $categories = $this->normalizeCategories($params['categories'] ?? []);
        $moduleId = (string)($params['module'] ?? '');
        $rootPageId = (string)($params['root'] ?? '');

        $options = $this->getOptions($moduleId, $rootPageId);
        $options['search_after'] = (string)($params['search_after'] ?? '');

        if (empty($categories)) {
            $categories = $options['categories'] ?? [];
        }

        $keywords = (new Keyword())->setKeywords((string)($params['query'] ?? ''), ['categories' => $categories]);

        $results = [
            'keywords' => $keywords,
            'results' => [],
        ];

        if ('' === $keywords['keyword']) {
            return $results;
        }

        // all credential types are served by the ProSearch proxy (no direct cluster connection)
        if (false !== (new Credentials())->getCredentials()) {
            $results['results'] = $this->searchService->search($keywords, $options, $this->indexNameResolver->getIndexName($rootPageId));
        }

        $hits = $results['results']['hits'] ?? [];
        $results['results']['hits'] = [];

        // cursor for the next page ("load more"), taken before grouping changes the order
        $lastHit = $hits ? $hits[array_key_last($hits)] : [];
        $results['results']['searchAfter'] = implode(',', array_map('strval', (array)($lastHit['sort'] ?? [])));

        $module = '' !== $moduleId ? ModuleModel::findByPk($moduleId) : null;
        $resultTemplate = $module ? ($module->psResultsTemplate ?: 'elasticsearch_result') : 'elasticsearch_result';

        if (true === ($params['group'] ?? false)) {
            $results['results']['hits'] = $this->groupHits($hits, (new Categories())->getTranslatedCategories($rootPageId), $keywords, $options, $resultTemplate);
        } else {
            foreach ($hits as $hit) {
                if ($parsed = $this->parseHit($hit, $keywords, $options)) {
                    $results['results']['hits'][] = $this->addTemplate($resultTemplate, $parsed);
                }
            }
        }

        if (isset($results['results']['didYouMean'])) {
            $results['results']['didYouMean'] = array_values(Toolkit::parseDidYouMeanArray($keywords['keyword'], $results['results']['didYouMean']));
        }

        if (!isset($results['results']['autocomplete'])) {
            $results['results']['autocomplete'] = [];
        }

        Stats::setKeyword($keywords, \count($results['results']['hits']), (string)($params['source'] ?? ''));

        if (isset($GLOBALS['TL_HOOKS']['psParseSearchResults']) && \is_array($GLOBALS['TL_HOOKS']['psParseSearchResults'])) {
            foreach ($GLOBALS['TL_HOOKS']['psParseSearchResults'] as $callback) {
                $results = System::importStatic($callback[0])->{$callback[1]}($results, $options, $context ?? $this);
            }
        }

        return $results;
    }

    public function autocomplete(array $params): array
    {
        $rootPageId = (string)($params['root'] ?? '');
        $keywords = (new Keyword())->setKeywords((string)($params['query'] ?? ''), ['categories' => $this->normalizeCategories($params['categories'] ?? [])]);

        $results = [
            'keywords' => $keywords,
            'results' => [],
        ];

        if (false !== (new Credentials())->getCredentials()) {
            $results['results'] = $this->searchService->autocomplete($keywords, $this->getOptions((string)($params['module'] ?? ''), $rootPageId), $this->indexNameResolver->getIndexName($rootPageId));
        }

        if (isset($results['results']['didYouMean'])) {
            $results['results']['didYouMean'] = array_values(Toolkit::parseDidYouMeanArray($keywords['keyword'], $results['results']['didYouMean']));
        }

        return $results;
    }

    public function getOptions(string $moduleId, string $rootPageId = ''): array
    {
        $module = '' !== $moduleId ? ModuleModel::findByPk($moduleId) : null;

        $options = new Options();
        $options->setLanguage($module?->psLanguage ?: '');
        $options->setRootPageId($rootPageId);
        $options->setPerPage($module?->perPage ?: 50);
        $options->setAnalyzer();
        $options->setCategories(StringUtil::deserialize($module?->psSearchCategories, true));
        $options->setFuzzy((bool)$module?->fuzzy);
        $options->setOpenDocumentsInBrowser((bool)$module?->psOpenDocumentInBrowser);
        $options->setMinKeywordLength($module?->minKeywordLength ?: 3);
        $options->setDomain($module?->psDomains ?: '');
        $result = $options->getOptions();

        // analyze the query like the index was built and filter categories on the exact field
        if (null !== $this->schemaResolver) {
            $schema = $this->schemaResolver->getSchema($this->indexNameResolver->getIndexName($rootPageId), $rootPageId);
            $result['analyzer'] = $schema['analyzer'];
            $result['typesField'] = $schema['typesField'];
        }

        return $result;
    }

    private function groupHits(array $hits, array $labels, array $keywords, array $options, string $resultTemplate): array
    {
        $byCategory = [];

        foreach ($hits as $hit) {
            $parsed = $this->parseHit($hit, $keywords, $options);

            if (!$parsed) {
                continue;
            }

            $rendered = $this->addTemplate($resultTemplate, $parsed);
            $types = array_values(array_unique(array_filter(array_map(static fn($type): string => \is_scalar($type) ? (string)$type : '', (array)($hit['_source']['types'] ?? [])), static fn(string $type): bool => '' !== $type)));

            foreach ($types ?: [''] as $type) {
                $byCategory[$type] ??= [
                    'hits' => [],
                    'label' => '' === $type ? '' : (string)($labels[$type]['label'] ?? $type),
                    'value' => $type,
                ];
                $byCategory[$type]['hits'][] = $rendered;
            }
        }

        $labelCount = array_count_values(array_column($byCategory, 'label'));
        $grouped = [];

        foreach ($byCategory as $type => $group) {
            $key = $labelCount[$group['label']] > 1 && '' !== $type ? $group['label'] . ' (' . $type . ')' : $group['label'];
            $grouped[$key] = $group;
        }

        uksort($grouped, static fn(string $a, string $b): int => strnatcasecmp($a, $b));

        return $grouped;
    }

    private function normalizeCategories(mixed $categories): array
    {
        if (\is_string($categories)) {
            $categories = '' === $categories ? [] : [$categories];
        }

        if (!\is_array($categories)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static fn($value): string => \is_scalar($value) ? Categories::normalize((string)$value) : '', $categories), static fn(string $value): bool => '' !== $value)));
    }

    private function parseHit(array $hit, array $keywords, array $options): array
    {
        $entity = new Result();
        $entity->addHit((string)$hit['_source']['id'], $hit['highlight'] ?? [], [
            'types' => $hit['_source']['types'] ?? [],
            'score' => $hit['_score'] ?? 0,
            'sort' => $hit['sort'] ?? [],
            'keywords' => $keywords,
            'elasticOptions' => $options,
        ]);

        return $entity->getResult() ?: [];
    }

    private function addTemplate(string $template, array $hit): array
    {
        $objTemplate = new FrontendTemplate($template);
        $objTemplate->setData($hit);

        $hit['template'] = System::getContainer()->get('contao.insert_tag.parser')->replaceInline($objTemplate->parse());

        return $hit;
    }
}
