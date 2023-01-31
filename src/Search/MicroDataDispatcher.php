<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Article;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Dataset;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Event;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\FAQPage;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\JobPosting;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Person;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Product;
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
        (new Person())->dispatch($document->extractJsonLdScripts('https://schema.org', 'Person'), $indicesId);
        (new Event())->dispatch($document->extractJsonLdScripts('https://schema.org', 'Event'), $indicesId);
        (new Dataset())->dispatch($document->extractJsonLdScripts('https://schema.org', 'Dataset'), $indicesId);
        (new JobPosting())->dispatch($document->extractJsonLdScripts('https://schema.org', 'JobPosting'), $indicesId);
        (new Product())->dispatch($document->extractJsonLdScripts('https://schema.org', 'Product'), $indicesId);
    }
}