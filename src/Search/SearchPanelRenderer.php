<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Contao\FrontendTemplate;
use Contao\StringUtil;
use Contao\System;

class SearchPanelRenderer
{
    public const string TEMPLATE = 'ps_search_panel';

    public function render(array $result, array $context): array
    {
        $data = $this->buildData($result, $context);

        $template = new FrontendTemplate($context['template'] ?? self::TEMPLATE);
        $template->setData($data);

        return [
            'html' => $template->parse(),
            'status' => $data['statusText'],
            'total' => $data['total'],
            'count' => $data['count'],
            'hasMore' => $data['hasMore'],
            'searchAfter' => (string)($result['results']['searchAfter'] ?? ''),
            'error' => $data['error'],
        ];
    }

    public function buildData(array $result, array $context): array
    {
        // loaded by the page or ProSearchController::applyLanguage(); load it if a caller did not
        if (!isset($GLOBALS['TL_LANG']['MSC']['searchResultsCount'])) {
            System::loadLanguageFile('default');
        }

        $lang = $GLOBALS['TL_LANG']['MSC'] ?? [];

        // Keyword::setKeywords() escapes slashes, Contao\Input encodes special characters
        $keyword = trim(StringUtil::decodeEntities(str_replace('\\/', '/', (string)($result['keywords']['keyword'] ?? ''))));
        $results = $result['results'] ?? [];
        $grouped = (bool)($context['grouped'] ?? false);
        $activeCategories = array_values($context['activeCategories'] ?? []);
        $onlyCategories = array_values($context['onlyCategories'] ?? []);

        $groups = [];
        $urls = [];

        foreach ($this->getGroups($results['hits'] ?? [], $grouped) as $group) {
            $value = (string)($group['value'] ?? '');

            // the categories of the module and the active filter limit the groups shown (as before in the Vue component)
            if ($grouped && (($activeCategories && !\in_array($value, $activeCategories, true)) || ($onlyCategories && !\in_array($value, $onlyCategories, true)))) {
                continue;
            }

            $hits = [];

            foreach ($group['hits'] ?? [] as $hit) {
                $url = (string)($hit['usedUrl'] ?? $hit['url'] ?? '');
                $urls[$url] = true;
                $hits[] = [
                    'url' => StringUtil::specialchars($url),
                    'html' => (string)($hit['template'] ?? ''),
                ];
            }

            if ($hits) {
                $groups[] = [
                    'value' => StringUtil::specialchars($value),
                    'key' => $value,
                    'label' => StringUtil::specialchars(strip_tags((string)($group['label'] ?? ''))),
                    'hits' => $hits,
                ];
            }
        }

        if ($grouped && $onlyCategories) {
            // keep the order of the module categories
            usort($groups, static fn(array $a, array $b): int => array_search($a['key'], $onlyCategories, true) <=> array_search($b['key'], $onlyCategories, true));
        }

        $count = \count($urls);
        $total = max((int)($results['total'] ?? 0), $count);
        $error = isset($results['error']);
        $loaded = max(0, (int)($context['loaded'] ?? 0));

        if ($error) {
            $status = $lang['searchResultsError'] ?? 'The search is currently not available.';
        } elseif ('' === $keyword) {
            $status = '';
        } elseif (0 === $count && 0 === $loaded) {
            $status = $lang['noSearchResults'] ?? 'No search results found.';
        } else {
            $status = \sprintf(1 === $total ? ($lang['searchResultsCountOne'] ?? '1 result for “%2$s”') : ($lang['searchResultsCount'] ?? '%1$s results for “%2$s”'), $total, $keyword);
        }

        $categories = [];

        if ($grouped && ($context['categories'] ?? true)) {
            $counts = [];

            $groupLabels = [];

            foreach ($groups as $group) {
                $counts[$group['key']] = \count($group['hits']);
                $groupLabels[$group['key']] = $group['label'];
            }

            foreach ($counts as $value => $categoryCount) {
                // documents without category cannot be filtered
                if ('' === (string)$value) {
                    continue;
                }

                $option = $context['categoryOptions'][$value] ?? null;

                $categories[] = [
                    'key' => StringUtil::specialchars((string)$value),
                    // categories not (yet) in tl_ps_categories: label of the group (key as fallback)
                    'label' => \is_array($option) ? StringUtil::specialchars(strip_tags((string)($option['label'] ?? $value))) : (string)$groupLabels[$value],
                    'count' => $categoryCount,
                    'active' => \in_array((string)$value, $activeCategories, true),
                ];
            }
        }

        $headingLevel = min(6, max(2, (int)($context['headingLevel'] ?? 2)));

        return [
            'formId' => $context['formId'],
            'idPrefix' => $context['idPrefix'],
            'keywords' => StringUtil::specialchars($keyword),
            'hasKeywords' => '' !== $keyword,
            'grouped' => $grouped,
            'groups' => $groups,
            'groupHeading' => 'h' . $headingLevel,
            'count' => $count,
            'total' => $total,
            'loaded' => $loaded,
            'status' => StringUtil::specialchars($status),
            'statusText' => $status,
            'error' => $error,
            'didYouMean' => array_map(static fn($value): string => StringUtil::specialchars((string)$value), array_values(array_filter((array)($results['didYouMean'] ?? []), 'is_scalar'))),
            'categories' => $categories,
            'activeCategories' => array_map(static fn(string $value): string => StringUtil::specialchars($value), $activeCategories),
            'searchAfter' => StringUtil::specialchars((string)($results['searchAfter'] ?? '')),
            'hasMore' => !$error && $count > 0 && ($loaded + $count) < $total && '' !== (string)($results['searchAfter'] ?? ''),
            'labels' => [
                'didYouMean' => trim((string)($lang['didYouMeanLabel'] ?? 'Did you mean')),
                'categories' => $lang['searchResultsCategoriesHeadline'] ?? 'Categories',
                'more' => $lang['searchResultsMoreButton'] ?? 'Load more',
                'noResults' => $lang['noSearchResults'] ?? 'No search results found.',
                'results' => $lang['searchResultsHeadline'] ?? 'Search results',
                'hitsInCategory' => $lang['searchCategoryHits'] ?? '%s results',
            ],
        ];
    }

    private function getGroups(mixed $hits, bool $grouped): array
    {
        if (!\is_array($hits) || !$hits) {
            return [];
        }

        if (!$grouped) {
            return [['value' => '', 'label' => '', 'hits' => array_values($hits)]];
        }

        return array_values(array_filter($hits, 'is_array'));
    }
}