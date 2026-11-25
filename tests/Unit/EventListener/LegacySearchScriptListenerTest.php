<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\EventListener;

use Alnv\ProSearchIndexerContaoAdapterBundle\EventListener\LegacySearchScriptListener;
use Contao\CoreBundle\Routing\ScopeMatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\AttributesRequestMatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class LegacySearchScriptListenerTest extends TestCase
{
    public function testAddsTheScriptToPagesWithOldSearchCalls(): void
    {
        $html = "<html><head><title>x</title></head><body><script>this.\$http.post('/elastic/search/results?query='+q, {})</script></body></html>";
        $response = $this->dispatch($html);

        $this->assertMatchesRegularExpression('~<script src="/bundles/alnvprosearchindexercontaoadapter/prosearch-legacy\.js\?v=[^"]*"></script>\n</head>~', (string) $response->getContent());
        $this->assertStringNotContainsString('data-request-token', (string) $response->getContent(), 'no token: the page must stay cacheable');
    }

    public function testDetectsFetchCalls(): void
    {
        $response = $this->dispatch("<head></head><script>const r = await fetch('/elastic/search/autocompletion?query=' + q, {method: 'POST'});</script>");
        $this->assertStringContainsString('prosearch-legacy.js', (string) $response->getContent());
    }

    public function testDetectsTheNewPathsInLegacyTemplates(): void
    {
        // j_elasticsearch* of the bundle call /prosearch/search/* and /prosearch/stat/click since 4.0
        foreach (["this.\$http.post('/prosearch/search/results?query='+q, {})", "this.\$http.post('/prosearch/stat/click?query='+q, {})"] as $call) {
            $response = $this->dispatch('<head></head><script>'.$call.'</script>');
            $this->assertStringContainsString('prosearch-legacy.js', (string) $response->getContent(), $call);
        }
    }

    public function testLeavesOtherPagesAlone(): void
    {
        // new front end: endpoints only in the data attribute, behaviour in an external file
        $new = '<head></head><div data-prosearch="{&quot;endpoints&quot;:{&quot;results&quot;:&quot;/elastic/search/results&quot;}}"></div><script src="/bundles/x/prosearch-search.js"></script>';
        $this->assertSame($new, $this->dispatch($new)->getContent());

        $already = "<head><script src=\"/bundles/alnvprosearchindexercontaoadapter/prosearch-legacy.js\"></script></head><script>fetch('/elastic/search/results')</script>";
        $this->assertSame($already, $this->dispatch($already)->getContent());

        $json = "{\"x\": \"fetch('/elastic/search/results')</head>\"}";
        $this->assertSame($json, $this->dispatch($json, 'application/json')->getContent());

        $backend = "<head></head><script>fetch('/elastic/search/results')</script>";
        $this->assertSame($backend, $this->dispatch($backend, 'text/html', 'backend')->getContent());
    }

    private function dispatch(string $content, string $contentType = 'text/html; charset=UTF-8', string $scope = 'frontend'): Response
    {
        $request = Request::create('https://example.org/de/suche');
        $request->attributes->set('_scope', $scope);

        $response = new Response($content, 200, ['Content-Type' => $contentType]);
        $scopeMatcher = new ScopeMatcher(new AttributesRequestMatcher(['_scope' => 'backend']), new AttributesRequestMatcher(['_scope' => 'frontend']), new RequestStack());

        $listener = new LegacySearchScriptListener($scopeMatcher, sys_get_temp_dir());
        $listener(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        return $response;
    }
}
