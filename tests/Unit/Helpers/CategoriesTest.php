<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Helpers;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Keyword;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Synonyms;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Collector\PageDocumentCollector;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\DocumentPayloadBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

final class CategoriesTest extends TestCase
{
    public function testNormalizesKeys(): void
    {
        $this->assertSame('bauen-wohnen', Categories::normalize(' Bauen-Wohnen '));
        $this->assertSame('kinder_jugend', Categories::normalize('Kinder_Jugend'));
        $this->assertSame('mobilität', Categories::normalize('Mobilität'));
        $this->assertSame('pdf', Categories::normalize('P.D F'));
        $this->assertSame('ab', Categories::normalize('"a&amp;b"'));
        $this->assertSame(['page', 'bauen-wohnen'], Categories::normalizeList('Page, bauen-wohnen,,page , '));
    }

    public function testTranslationsWithRegionalLanguagesAndEmptyLabels(): void
    {
        $translations = serialize([
            ['language' => 'de', 'label' => 'Themenseiten'],
            ['language' => 'de_CH', 'label' => 'Themenseiten (CH)'],
            ['language' => 'en', 'label' => ''],
            ['language' => 'fr', 'label' => 'Pages &amp; thèmes'],
        ]);

        $this->assertSame('Themenseiten', Categories::translate('page', $translations, 'de'));
        $this->assertSame('Themenseiten (CH)', Categories::translate('page', $translations, 'de-CH'));
        $this->assertSame('Themenseiten', Categories::translate('page', $translations, 'de-AT'), 'primary language as fallback');
        $this->assertSame('page', Categories::translate('page', $translations, 'en'), 'empty label: key instead of an empty group name');
        $this->assertSame('Pages & thèmes', Categories::translate('page', $translations, 'fr'));
        $this->assertSame('page', Categories::translate('page', null, 'de'));
    }

    public function testCategoriesAreNormalizedEverywhere(): void
    {
        // collector (meta tag of the page)
        $crawler = new Crawler('<html><head><meta name="search:type" content="Page, Bauen-Wohnen"><meta name="search:type" content="page,pdf"></head></html>');
        $this->assertSame(['page', 'bauen-wohnen', 'pdf'], PageDocumentCollector::searchTypes($crawler));

        // payload sent to the proxy (old records may contain upper case or empty values)
        $payload = (new DocumentPayloadBuilder())->build(['id' => 1, 'types' => serialize(['Leistung', '', 'leistung', 'PDF'])]);
        $this->assertSame(['leistung', 'pdf'], $payload['types']);

        // active filter of a search
        $keywords = (new Keyword(new Synonyms([])))->setKeywords('kita', ['categories' => ['PDF', '', 'pdf', 'Page']]);
        $this->assertSame(['pdf', 'page'], $keywords['types']);
    }
}
