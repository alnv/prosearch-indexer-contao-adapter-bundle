<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Logger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Collector\PageDocumentCollector;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Collector\PdfDocumentCollector;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Search\Document;
use Contao\CoreBundle\Search\Indexer\IndexerException;
use Contao\CoreBundle\Search\Indexer\IndexerInterface;
use Contao\System;
use Psr\Log\LogLevel;

class ProSearchIndexer implements IndexerInterface
{
    private ContaoFramework $framework;

    private bool $indexProtected;

    public function __construct(
        ContaoFramework                $framework,
        bool                           $indexProtected = false,
        private ?PageDocumentCollector $pageCollector = null,
        private ?PdfDocumentCollector  $pdfCollector = null,
        private ?IndicesRepository     $repository = null,
    )
    {
        $this->framework = $framework;
        $this->indexProtected = $indexProtected;
    }

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
        } catch (\Exception) {
            $title = '';
        }

        try {
            $language = $document->getContentCrawler()->filterXPath('//html[@lang]')->first()->attr('lang');
        } catch (\Exception) {
            $language = 'en';
        }

        $meta = [
            'title' => $title,
            'language' => $language,
            'protected' => false,
            'groups' => [],
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

        // If the page is protected and indexing protecting pages is disabled, we do not index
        if (isset($meta['protected']) && true === $meta['protected'] && !$this->indexProtected) {
            $this->throwBecause('Indexing protected pages is disabled.');
        }

        $this->framework->initialize();

        $pageError = null;

        try {
            $this->pages()->collect($document, $meta);
        } catch (IndexerException $e) {
            $pageError = $e;
        } catch (\Throwable $e) {
            Logger::set('ProSearch: indexing ' . $document->getUri() . ' failed: ' . $e->getMessage(), LogLevel::ERROR, __METHOD__);
            $pageError = new IndexerException('Indexing failed: ' . $e->getMessage(), 0, $e);
        }

        // linked PDFs are captured even if the page itself is skipped (e.g. canonical page)
        try {
            $this->pdfs()->collect($document, $meta);
        } catch (\Throwable $e) {
            Logger::set('ProSearch: indexing the PDF files of ' . $document->getUri() . ' failed: ' . $e->getMessage(), LogLevel::ERROR, __METHOD__);
        }

        if (null !== $pageError) {
            throw $pageError;
        }
    }

    public function delete(Document $document): void
    {
        $this->framework->initialize();
        $this->repo()->markDeletedByUrl(PageDocumentCollector::normalizeUrl($document->getUri()->__toString()));
    }

    public function clear(): void
    {
        $this->framework->initialize();
        $this->repo()->markAllDeleted();
    }

    private function pages(): PageDocumentCollector
    {
        return $this->pageCollector ??= System::getContainer()->get(PageDocumentCollector::class);
    }

    private function pdfs(): PdfDocumentCollector
    {
        return $this->pdfCollector ??= System::getContainer()->get(PdfDocumentCollector::class);
    }

    private function repo(): IndicesRepository
    {
        return $this->repository ??= System::getContainer()->get(IndicesRepository::class);
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
