<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Article;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\FAQPage;
use Contao\CoreBundle\Search\Document;

/**
 *
 */
class MicroDataDispatcher
{

    /**
     * @param Document $document
     * @param int $indicesId
     */
    public function __construct(Document $document, int $indicesId)
    {
        (new FAQPage())->dispatch($document->extractJsonLdScripts('https://schema.org', 'FAQPage'), $indicesId);
        (new Article())->dispatch($document->extractJsonLdScripts('https://schema.org', 'Article'), $indicesId);
    }
}