<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexSchemaResolver;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\MappingFactory;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\SearchService;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

class IndexSchemaResolverTest extends TestCase
{
    public function testAnalyzerIsReadFromTheTextField(): void
    {
        $schema = IndexSchemaResolver::fromProperties(['text' => ['type' => 'text', 'analyzer' => 'german']]);

        $this->assertSame('german', $schema['analyzer']);
    }

    public function testSearchAnalyzerWinsOverTheIndexAnalyzer(): void
    {
        $schema = IndexSchemaResolver::fromProperties(['text' => ['type' => 'text', 'analyzer' => 'german', 'search_analyzer' => 'contao']]);

        $this->assertSame('contao', $schema['analyzer']);
    }

    public function testTextFieldWithoutAnalyzerUsesStandard(): void
    {
        $this->assertSame('standard', IndexSchemaResolver::fromProperties(['text' => ['type' => 'text']], 'german')['analyzer']);
    }

    public function testMissingTextFieldUsesTheFallback(): void
    {
        $this->assertSame('german', IndexSchemaResolver::fromProperties([], 'german')['analyzer']);
    }

    public function testDynamicTextMappingOfTypesFiltersOnTheKeywordSubField(): void
    {
        // indices before 4.0: "types" was mapped dynamically, "bauen-wohnen" would be split into two terms
        $schema = IndexSchemaResolver::fromProperties([
            'types' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword', 'ignore_above' => 256]]],
        ]);

        $this->assertSame('types.keyword', $schema['typesField']);
    }

    public function testKeywordMappingOfTypesFiltersOnTypes(): void
    {
        $this->assertSame('types', IndexSchemaResolver::fromProperties(['types' => ['type' => 'keyword']])['typesField']);
        $this->assertSame('types', IndexSchemaResolver::fromProperties([])['typesField']);
    }

    public function testInvalidAnalyzerNamesFallBackToTheDefault(): void
    {
        $this->assertSame('german', IndexSchemaResolver::sanitize(' german '));
        $this->assertSame(IndexSchemaResolver::DEFAULT_ANALYZER, IndexSchemaResolver::sanitize(''));
        $this->assertSame(IndexSchemaResolver::DEFAULT_ANALYZER, IndexSchemaResolver::sanitize('german"}'));
    }

    public function testNewIndicesMapTypesAsKeyword(): void
    {
        $params = (new MappingFactory())->create('contao_search_test_1', ['analyzer' => 'german']);
        $properties = $params['body']['mappings']['properties'];

        $this->assertSame(['type' => 'keyword'], $properties['types']);
        $this->assertSame('german', $properties['text']['analyzer']);
        $this->assertSame(['analyzer' => 'german', 'typesField' => 'types'], IndexSchemaResolver::fromProperties($properties));
    }

    public function testLanguageAnalyzersUseTheStandardTokenizerAndKnownFilters(): void
    {
        $params = (new MappingFactory())->create('contao_search_test_1', ['analyzer' => 'german']);
        $analysis = $params['body']['settings']['analysis'];
        $builtIn = ['lowercase', 'german_normalization'];

        foreach (['german', 'english', 'french'] as $name) {
            $this->assertSame('standard', $analysis['analyzer'][$name]['tokenizer'], $name);

            foreach ($analysis['analyzer'][$name]['filter'] as $filter) {
                $this->assertTrue(\in_array($filter, $builtIn, true) || isset($analysis['filter'][$filter]), "$name: filter $filter is not defined");
            }
        }

        $this->assertSame('whitespace', $analysis['analyzer']['contao']['tokenizer']);
    }

    public function testCategoryFilterUsesTheFieldOfTheIndex(): void
    {
        $service = new SearchService(new ProxyClient(new MockHttpClient(), 'https://proxy.example'), $this->createMock(LicenseProvider::class));

        $request = $service->createRequest(['query' => 'kita', 'types' => ['Bauen-Wohnen']], ['typesField' => 'types.keyword'], 'search');
        $this->assertSame(['types.keyword' => ['bauen-wohnen']], $request['filters']);

        $request = $service->createRequest(['query' => 'kita', 'types' => ['pdf']], [], 'search');
        $this->assertSame(['types' => ['pdf']], $request['filters']);

        // no arbitrary field names from the options
        $request = $service->createRequest(['query' => 'kita', 'types' => ['pdf']], ['typesField' => 'title'], 'search');
        $this->assertSame(['types' => ['pdf']], $request['filters']);
    }
}
