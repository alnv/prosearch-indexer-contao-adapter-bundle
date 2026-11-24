<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Search\SearchPanelRenderer;
use PHPUnit\Framework\TestCase;

final class SearchPanelRendererTest extends TestCase
{
    private array|null $lang = null;

    protected function setUp(): void
    {
        $this->lang = $GLOBALS['TL_LANG']['MSC'] ?? null;
        $GLOBALS['TL_LANG']['MSC'] = [
            'searchResultsCount' => '%1$s Ergebnisse für „%2$s“',
            'searchResultsCountOne' => '1 Ergebnis für „%2$s“',
            'noSearchResults' => 'Keine Suchergebnisse gefunden.',
            'searchResultsError' => 'Die Suche ist gerade nicht verfügbar.',
            'didYouMeanLabel' => 'Meinten Sie ',
        ];
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_LANG']['MSC'] = $this->lang;
    }

    public function testGroupsAreFilteredOrderedAndCounted(): void
    {
        $data = (new SearchPanelRenderer())->buildData($this->searchResult([
            'Downloads' => ['value' => 'pdf', 'label' => 'Downloads', 'hits' => [$this->hit('/a.pdf'), $this->hit('/b.pdf')]],
            'Seiten' => ['value' => 'page', 'label' => 'Seiten', 'hits' => [$this->hit('/c')]],
            'News' => ['value' => 'news', 'label' => 'News', 'hits' => [$this->hit('/d')]],
        ], 4), $this->context(['onlyCategories' => ['page', 'pdf'], 'categoryOptions' => ['pdf' => ['label' => 'Downloads'], 'page' => ['label' => '<b>Seiten</b>']]]));

        // only the module categories, in their order
        $this->assertSame(['page', 'pdf'], array_column($data['groups'], 'key'));
        $this->assertSame(3, $data['count']);
        $this->assertSame([['key' => 'page', 'label' => 'Seiten', 'count' => 1, 'active' => false], ['key' => 'pdf', 'label' => 'Downloads', 'count' => 2, 'active' => false]], $data['categories']);
        $this->assertSame('4 Ergebnisse für „Kita“', $data['statusText']);
        $this->assertSame('h3', $data['groupHeading']);
    }

    public function testCategoriesWithoutTableEntryGetAFilterButtonDocumentsWithoutCategoryNot(): void
    {
        $data = (new SearchPanelRenderer())->buildData($this->searchResult([
            'neu' => ['value' => 'neu', 'label' => 'neu', 'hits' => [$this->hit('/n')]],
            '' => ['value' => '', 'label' => '', 'hits' => [$this->hit('/x')]],
            'Downloads' => ['value' => 'pdf', 'label' => 'Downloads', 'hits' => [$this->hit('/a.pdf')]],
        ], 3), $this->context(['categoryOptions' => ['pdf' => ['label' => 'Downloads']]]));

        $this->assertSame(['neu', '', 'pdf'], array_column($data['groups'], 'key'));
        $this->assertSame([['key' => 'neu', 'label' => 'neu', 'count' => 1, 'active' => false], ['key' => 'pdf', 'label' => 'Downloads', 'count' => 1, 'active' => false]], $data['categories']);
    }

    public function testActiveCategoryFilterKeepsOnlyMatchingGroups(): void
    {
        $data = (new SearchPanelRenderer())->buildData($this->searchResult([
            'Downloads' => ['value' => 'pdf', 'label' => 'Downloads', 'hits' => [$this->hit('/a.pdf')]],
            'Seiten' => ['value' => 'page', 'label' => 'Seiten', 'hits' => [$this->hit('/c')]],
        ], 2), $this->context(['activeCategories' => ['pdf'], 'categoryOptions' => ['pdf' => ['label' => 'Downloads']]]));

        $this->assertSame(['pdf'], array_column($data['groups'], 'key'));
        $this->assertTrue($data['categories'][0]['active']);
        $this->assertSame(['pdf'], $data['activeCategories']);
    }

    public function testUserInputIsEscapedAndDecodedForTheStatus(): void
    {
        // Contao\Input encodes special characters, Keyword escapes slashes
        $result = $this->searchResult([], 1, '&#34;&#62;&#60;img src&#61;x&#62; a\/b');
        $result['results']['hits'] = [$this->hit('/x"><script>')];
        $result['results']['didYouMean'] = ['<i>kita</i>'];

        $data = (new SearchPanelRenderer())->buildData($result, $this->context(['grouped' => false]));

        $this->assertSame('1 Ergebnis für „"><img src=x> a/b“', $data['statusText']);
        $this->assertStringNotContainsString('<img', $data['status']);
        $this->assertStringNotContainsString('<img', $data['keywords']);
        $this->assertSame('/x&quot;&gt;&lt;script&gt;', $data['groups'][0]['hits'][0]['url']);
        $this->assertSame(['&lt;i&gt;kita&lt;/i&gt;'], $data['didYouMean']);
    }

    public function testFlatListWithMoreResults(): void
    {
        $result = $this->searchResult([], 12);
        $result['results']['hits'] = [$this->hit('/1'), $this->hit('/2')];
        $result['results']['searchAfter'] = '1.5,https://x/2';

        $data = (new SearchPanelRenderer())->buildData($result, $this->context(['grouped' => false]));
        $this->assertTrue($data['hasMore']);
        $this->assertSame('1.5,https://x/2', $data['searchAfter']);
        $this->assertSame([], $data['categories']);
        $this->assertSame('', $data['groups'][0]['label']);

        // page 6 of 6: nothing more to load
        $data = (new SearchPanelRenderer())->buildData($result, $this->context(['grouped' => false, 'loaded' => 10]));
        $this->assertFalse($data['hasMore']);
    }

    public function testNoResultsAndErrors(): void
    {
        $renderer = new SearchPanelRenderer();

        $data = $renderer->buildData($this->searchResult([], 0), $this->context());
        $this->assertSame('Keine Suchergebnisse gefunden.', $data['statusText']);
        $this->assertFalse($data['hasMore']);

        $error = $this->searchResult([], 0);
        $error['results']['error'] = 'proxy_unavailable';
        $data = $renderer->buildData($error, $this->context());
        $this->assertTrue($data['error']);
        $this->assertSame('Die Suche ist gerade nicht verfügbar.', $data['statusText']);

        $data = $renderer->buildData($this->searchResult([], 0, ''), $this->context());
        $this->assertSame('', $data['statusText']);
        $this->assertFalse($data['hasKeywords']);
    }

    private function searchResult(array $hits, int $total, string $keyword = 'Kita'): array
    {
        return [
            'keywords' => ['keyword' => $keyword],
            'results' => ['hits' => $hits, 'total' => $total, 'didYouMean' => []],
        ];
    }

    private function hit(string $url): array
    {
        return ['url' => $url, 'usedUrl' => $url, 'template' => '<a href="'.$url.'">x</a>'];
    }

    private function context(array $context = []): array
    {
        return $context + [
            'formId' => 'ps_search_form_1',
            'idPrefix' => 'id_search_1',
            'grouped' => true,
            'headingLevel' => 3,
        ];
    }
}
