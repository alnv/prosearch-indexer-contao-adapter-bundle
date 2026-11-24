<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\MappingFactory;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\SearchService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SearchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        unset($GLOBALS['TL_HOOKS']['psProxySearchRequest'], $GLOBALS['TL_HOOKS']['psSearchQuery']);
    }

    public function testBuildsTheProxyRequestFromAdapterOptions(): void
    {
        $request = $this->service(new MockHttpClient())->createRequest(
            ['query' => 'bürger', 'types' => ['News', 'Amt']],
            ['analyzer' => 'german', 'fuzzy' => true, 'language' => 'de', 'domain' => 'www.a.de, a.de', 'perPage' => 0, 'search_after' => '1.5,https://x'],
            SearchService::MODE_SEARCH,
        );

        $this->assertSame([
            'query' => 'bürger',
            'profile' => 'prosearch_default',
            'params' => ['analyzer' => 'german', 'fuzzy' => true],
            'filters' => ['language' => 'de', 'domain' => ['www.a.de', 'a.de'], 'types' => ['news', 'amt']],
            'size' => 500,
            'search_after' => ['1.5', 'https://x'],
        ], $request);
    }

    public function testSynonymsAreSentAsAlternativesIfTheProxySupportsThem(): void
    {
        $calls = 0;
        $client = new MockHttpClient(function (string $method, string $url) use (&$calls): MockResponse {
            ++$calls;
            $this->assertStringEndsWith('/api/v1/search/capabilities', $url);

            return new MockResponse(json_encode(['profiles' => [], 'profile_params' => ['prosearch_default' => ['analyzer', 'fuzzy', 'synonyms']]]));
        });
        $cache = new ArrayAdapter();
        $keywords = ['query' => 'hartz 4', 'synonym' => 'bürgergeld', 'synonyms' => ['bürgergeld', 'jobcenter']];

        $request = $this->service($client, $cache)->createRequest($keywords, [], SearchService::MODE_SEARCH);
        $this->assertSame('hartz 4', $request['query']);
        $this->assertSame(['bürgergeld', 'jobcenter'], $request['params']['synonyms']);

        // suggestions refer to the typed text
        $request = $this->service($client, $cache)->createRequest($keywords, [], SearchService::MODE_AUTOCOMPLETE);
        $this->assertSame('hartz 4', $request['query']);
        $this->assertArrayNotHasKey('synonyms', $request['params']);

        // capabilities are cached (second service instance, same pool)
        $this->assertSame(1, $calls);

        // no synonyms: no capabilities request at all
        $this->service(new MockHttpClient(fn () => $this->fail('unexpected request')))->createRequest(['query' => 'kita', 'synonyms' => []], [], SearchService::MODE_SEARCH);
    }

    public function testOldProxyGetsTheReplacedQuery(): void
    {
        $keywords = ['query' => 'hartz 4', 'synonym' => 'bürgergeld', 'synonyms' => ['bürgergeld']];

        // proxy < 4.1: no profile_params
        $old = new MockHttpClient(new MockResponse(json_encode(['profiles' => ['prosearch_default' => '…']])));
        $request = $this->service($old)->createRequest($keywords, [], SearchService::MODE_SEARCH);
        $this->assertSame('bürgergeld', $request['query']);
        $this->assertArrayNotHasKey('synonyms', $request['params']);

        // capabilities not reachable: same fallback, the search itself still runs
        $down = new MockHttpClient(new MockResponse('', ['http_code' => 503]));
        $request = $this->service($down)->createRequest($keywords, [], SearchService::MODE_SEARCH);
        $this->assertSame('bürgergeld', $request['query']);
    }

    public function testAutocompleteRequestHasNoPaging(): void
    {
        $request = $this->service(new MockHttpClient())->createRequest(['query' => 'bü'], ['analyzer' => ''], SearchService::MODE_AUTOCOMPLETE);

        $this->assertSame(['query' => 'bü', 'profile' => 'prosearch_default', 'params' => ['analyzer' => 'standard', 'fuzzy' => false]], $request);
    }

    public function testMapsProxyResultToTheFormerResultFormat(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'hits' => [['id' => '12', 'index' => 'contao_search_a', 'score' => 3.5, 'source' => ['id' => '12', 'types' => ['news']], 'highlight' => ['text' => ['<strong>b</strong>']], 'sort' => [3.5, 'u']]],
            'total' => 1, 'max_score' => 3.5, 'step' => 1, 'steps' => 2,
            'suggestions' => ['autocomplete' => [['text' => 'bürgeramt', 'count' => 2]], 'did_you_mean' => ['bürger']],
        ])));

        $result = $this->service($client)->search(['query' => 'bürgr'], [], 'contao_search_a');

        $this->assertSame(1, $result['tries']);
        $this->assertSame(3.5, $result['max_score']);
        $this->assertSame(['_index' => 'contao_search_a', '_id' => '12', '_score' => 3.5, '_source' => ['id' => '12', 'types' => ['news']], 'highlight' => ['text' => ['<strong>b</strong>']], 'sort' => [3.5, 'u']], $result['hits'][0]);
        $this->assertSame(['bürger'], $result['didYouMean']);
        $this->assertSame([['term' => 'bürgeramt', 'template' => 'bürgeramt']], $result['autocomplete']);
    }

    public function testUnreachableProxyYieldsAnEmptyResult(): void
    {
        $result = $this->service(new MockHttpClient(new MockResponse('', ['error' => 'Connection refused'])))->search(['query' => 'x'], [], 'contao_search_a');

        $this->assertSame([], $result['hits']);
        $this->assertSame('proxy_unreachable', $result['error']);

        $autocomplete = $this->service(new MockHttpClient(new MockResponse('{"error":{"code":"license_invalid","message":"no"}}', ['http_code' => 403])))->autocomplete(['query' => 'x'], [], 'contao_search_a');
        $this->assertSame(['hits' => [], 'didYouMean' => []], $autocomplete);
    }

    public function testAutocompleteUsesTheFeatureEndpoint(): void
    {
        $client = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertStringEndsWith('/api/v1/indices/contao_search_a/search/autocomplete', $url);

            return new MockResponse('{"items":[],"suggestions":{"autocomplete":[{"text":"kfz","count":1}],"did_you_mean":[]}}');
        });

        $this->assertSame(['hits' => [['term' => 'kfz', 'template' => 'kfz']], 'didYouMean' => []], $this->service($client)->autocomplete(['query' => 'kf'], [], 'contao_search_a'));
    }

    public function testMappingIsUnchanged(): void
    {
        $params = (new MappingFactory())->create('contao_search_a', ['analyzer' => 'contao']);

        $this->assertSame('contao_search_a', $params['index']);
        $this->assertSame(['german', 'english', 'french', 'contao'], array_keys((new MappingFactory())->getAnalyzers()));
        $this->assertSame(['type' => 'text', 'fielddata' => true, 'analyzer' => 'autocomplete'], $params['body']['mappings']['properties']['autocomplete']);
        $this->assertSame(['type' => 'text', 'analyzer' => 'contao', 'copy_to' => ['autocomplete']], $params['body']['mappings']['properties']['title']);
        $this->assertSame(['type' => 'text', 'analyzer' => 'contao'], $params['body']['mappings']['properties']['text']);
        $this->assertSame(['type' => 'keyword'], $params['body']['mappings']['properties']['url']);
        $this->assertSame(['max_shingle_size' => 4, 'min_shingle_size' => 2, 'type' => 'shingle'], $params['body']['settings']['analysis']['filter']['autocomplete']);
    }

    private function service(MockHttpClient $client, ?ArrayAdapter $cache = null): SearchService
    {
        $licenseProvider = new class extends LicenseProvider {
            public function getLicenseIdentifier(?string $domain = null): string
            {
                return 'LICENSE';
            }
        };

        return new SearchService(new ProxyClient($client, 'https://proxy.example'), $licenseProvider, 'prosearch_default', $cache);
    }
}
