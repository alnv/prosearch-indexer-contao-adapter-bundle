<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\CoreBundle\Search\Document;
use Contao\CoreBundle\Search\Indexer\IndexerException;
use Contao\CoreBundle\Search\Indexer\IndexerInterface;
use Contao\StringUtil;

/**
 * https://docs.contao.org/dev/framework/search-indexing/
 */
class ProSearchIndexer implements IndexerInterface
{
    public function index(Document $document): void
    {

        if (200 !== $document->getStatusCode()) {
            $this->throwBecause('HTTP Statuscode is not equal to 200.');
        }

        if ('' === $document->getBody()) {
            $this->throwBecause('Cannot index empty response.');
        }

        try {
            $title = $document->getContentCrawler()->filterXPath('//head/title')->first()->text(null, true);
        } catch (\Exception $e) {
            $title = '';
        }

        try {
            $language = $document->getContentCrawler()->filterXPath('//html[@lang]')->first()->attr('lang');
        } catch (\Exception $e) {
            $language = 'en';
        }

        $meta = [
            'title' => $title,
            'language' => $language,
            'protected' => false,
            'groups' => []
        ];

        $this->extendMetaFromJsonLdScripts($document, $meta);

        if (!isset($meta['pageId']) || 0 === $meta['pageId']) {
            $this->throwBecause('No page ID could be determined.');
        }

        // If search was disabled in the page settings, we do not index
        if (isset($meta['noSearch']) && true === $meta['noSearch']) {
            $this->throwBecause('Was explicitly marked "noSearch" in page settings.');
        }

        // If the front end preview is activated, we do not index
        if (isset($meta['fePreview']) && true === $meta['fePreview']) {
            $this->throwBecause('Indexing when the front end preview is enabled is not possible.');
        }

        new Indices($document, $meta);
        new PDFIndices($document, $meta);
    }

    public function delete(Document $document): void
    {
        $strUrl = $document->getUri()->__toString();
        $strUrl = StringUtil::decodeEntities($strUrl);
        $strUrl = strtok($strUrl, '?');

        $objIndices = IndicesModel::findByUrl($strUrl);

        if (!$objIndices) {
            return;
        }

        $objIndices->state = States::DELETE;
        $objIndices->save();
    }

    public function clear(): void
    {
        $objIndices = IndicesModel::findAll();

        if (!$objIndices) {
            return;
        }

        while ($objIndices->next()) {
            $objIndices->state = States::DELETE;
            $objIndices->save();
        }
    }

    private function throwBecause(string $message, bool $onlyWarning = true): void
    {
        if ($onlyWarning) {
            throw IndexerException::createAsWarning($message);
        }

        throw new IndexerException($message);
    }

    private function extendMetaFromJsonLdScripts(Document $document, array &$meta): void
    {
        $jsonLds = $document->extractJsonLdScripts('https://schema.contao.org/', 'Page');

        if (0 === \count($jsonLds)) {
            $jsonLds = $document->extractJsonLdScripts('https://schema.contao.org/', 'RegularPage');

            if (0 === \count($jsonLds)) {
                $this->throwBecause('No JSON-LD found.');
            }
        }

        $meta = array_merge($meta, array_merge(...$jsonLds));
    }
}