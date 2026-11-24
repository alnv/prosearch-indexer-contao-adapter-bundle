<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps search front ends of ProSearch < 4 working after the endpoints became POST + CSRF only.
 *
 * Custom templates, layout scripts or content elements of older installations call /elastic/search/* or
 * /stat/click directly (Vue/vue-resource, fetch) without a request token. If a front end page contains such a
 * call, prosearch-legacy.js is added to the head: it adds the token to these requests (see the script).
 * Pages using the new front end (prosearch-search.js) do not need it.
 *
 * No token is embedded on purpose: requesting one would make Contao set the CSRF cookie and turn cacheable
 * pages into private responses. The script uses the token of a form on the page (kept fresh by Contao's
 * request token script) or the fresh token sent with the first 403 response and repeats that request.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: 0)]
class LegacySearchScriptListener
{
    private const string SCRIPT = 'bundles/alnvprosearchindexercontaoadapter/prosearch-legacy.js';

    // inline calls of the old templates, e.g. this.$http.post('/elastic/search/results?query=' …) or fetch('/elastic/search/autocompletion?query=' …)
    private const string LEGACY_CALL = '~(?:\$http\.(?:post|get)|fetch)\(\s*[\'"][^\'"]*/(?:elastic/search|stat/click)/?~';

    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->scopeMatcher->isFrontendRequest($event->getRequest())) {
            return;
        }

        $response = $event->getResponse();

        if (!str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')) {
            return;
        }

        $content = $response->getContent();

        if (!\is_string($content) || !str_contains($content, '</head>') || str_contains($content, 'prosearch-legacy.js') || !preg_match(self::LEGACY_CALL, $content)) {
            return;
        }

        $version = @filemtime($this->projectDir.'/public/'.self::SCRIPT) ?: @filemtime(__DIR__.'/../Resources/public/prosearch-legacy.js') ?: '';
        $tag = \sprintf('<script src="%s/%s?v=%s"></script>', $event->getRequest()->getBasePath(), self::SCRIPT, $version);

        // at the end of the head: after Vue/vue-resource of the layout, before the inline scripts in the body
        $position = strpos($content, '</head>');
        $response->setContent(substr_replace($content, $tag."\n", $position, 0));
    }
}
