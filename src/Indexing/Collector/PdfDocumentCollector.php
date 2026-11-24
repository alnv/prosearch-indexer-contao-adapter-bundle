<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Collector;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Logger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction\DocumentExtractionException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction\PdfTextExtractor;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction\TextNormalizer;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndexDocument;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SaveResult;
use Contao\CoreBundle\Search\Document;
use Contao\FilesModel;
use Contao\Frontend;
use Contao\PageModel;
use Contao\StringUtil;
use Psr\Log\LogLevel;

class PdfDocumentCollector
{
    private array $handled = [];

    public function __construct(
        private readonly IndicesRepository $repository,
        private readonly PdfTextExtractor  $extractor,
        private readonly string            $projectDir,
    )
    {
    }

    public function collect(Document $document, array $meta): array
    {
        $crawler = $document->getContentCrawler();
        $pageUrl = $document->getUri()->__toString();
        $language = (string)($meta['language'] ?? '') ?: PageDocumentCollector::language($crawler);
        $domain = self::origin($pageUrl);
        $page = null;
        $results = [];

        foreach ($crawler->filterXPath('//a[@href]') as $link) {
            $href = (string)$link->getAttribute('href');
            $path = self::filePath($href);

            if (null === $path) {
                continue;
            }

            $url = $domain . '/' . $path;

            if (isset($this->handled[$url])) {
                continue;
            }

            $this->handled[$url] = true;

            try {
                $file = FilesModel::findByPath($path) ?? FilesModel::findByPath(rawurldecode($path));

                if (null === $file) {
                    continue;
                }

                if (null === $page) {
                    $page = PageModel::findById((int)($meta['pageId'] ?? 0));
                    $page?->loadDetails();
                }

                $url = $domain . '/' . $file->path;
                $results[] = $this->collectFile($file, $url, $link, $language, $document, $page, $meta);
            } catch (\Throwable $e) {
                Logger::set(\sprintf('ProSearch PDF (%s): %s', $path, $e->getMessage()), LogLevel::ERROR, __METHOD__);
            }
        }

        return $results;
    }

    public function reset(): void
    {
        $this->handled = [];
    }

    private function collectFile(FilesModel $file, string $url, \DOMElement $link, string $language, Document $document, ?PageModel $page, array $meta): SaveResult
    {
        $absolutePath = $this->projectDir . '/' . $file->path;
        $sourceHash = hash('sha256', ($file->hash ?? '') . '|' . (is_file($absolutePath) ? filesize($absolutePath) . '|' . filemtime($absolutePath) : 'missing'));
        $state = $this->repository->findSourceState($url);

        $fileMeta = Frontend::getMetaData(StringUtil::deserialize($file->meta), $language);
        $linkText = TextNormalizer::singleLine((string)$link->textContent);
        $titleAttribute = TextNormalizer::singleLine((string)$link->getAttribute('title'));
        $metaTitle = TextNormalizer::singleLine((string)($fileMeta['title'] ?? ''));
        $caption = TextNormalizer::singleLine((string)($fileMeta['caption'] ?? ''));
        $alt = TextNormalizer::singleLine((string)($fileMeta['alt'] ?? ''));
        $filename = basename($file->path);

        $text = null;

        // unchanged file: keep the stored text and chunks
        if (null === $state || $state['source_hash'] !== $sourceHash || '' === $state['content_hash']) {
            try {
                $text = $this->extractor->extract($absolutePath);

                if ('' === $text) {
                    Logger::set(\sprintf('ProSearch PDF (%s): no text layer found (scanned document?), only title and meta data are indexed.', $file->path), LogLevel::INFO, __METHOD__);
                }
            } catch (DocumentExtractionException $e) {
                Logger::set(\sprintf('ProSearch PDF (%s): %s Only title and meta data are indexed.', $file->path, $e->getMessage()), LogLevel::ERROR, __METHOD__);

                if (null !== $state && hash('sha256', '') !== $state['content_hash'] && '' !== $state['content_hash']) {
                    // keep the last readable version in the index
                    return new SaveResult(SaveResult::UNCHANGED, $state['id']);
                }

                // index title and meta data so the file can still be found
                $text = '';
            }
        }

        $strong = array_values(array_unique(array_filter([$linkText, $titleAttribute, $caption, $metaTitle, $alt, $filename])));

        return $this->repository->save(new IndexDocument(
            url: $url,
            domain: $document->getUri()->getHost(),
            docType: IndexDocument::TYPE_FILE,
            language: $language,
            pageId: (int)($page?->id ?? ($meta['pageId'] ?? 0)),
            rootId: (int)($page?->rootId ?? 0),
            title: $metaTitle ?: ($titleAttribute ?: ($linkText ?: $filename)),
            description: $caption ?: $alt,
            images: ['assets/contao/images/pdf.svg'],
            types: ['pdf'],
            text: $text,
            strong: $strong,
            headings: IndexDocument::emptyHeadings(),
            originUrl: $document->getUri()->__toString(),
            protected: (bool)($meta['protected'] ?? false),
            groups: array_values(array_map('strval', (array)($meta['groups'] ?? []))),
            sourceHash: $sourceHash,
        ));
    }

    public static function filePath(string $href): ?string
    {
        $parts = parse_url(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (false === $parts) {
            return null;
        }

        $path = (string)($parts['path'] ?? '');
        parse_str((string)($parts['query'] ?? ''), $query);

        if (str_ends_with(strtolower($path), '.pdf')) {
            return ltrim($path, '/');
        }

        foreach (['p' => 'files/', 'file' => '', 'f' => ''] as $parameter => $prefix) {
            $value = $query[$parameter] ?? null;

            if (\is_string($value) && str_ends_with(strtolower($value), '.pdf')) {
                return $prefix . ltrim($value, '/');
            }
        }

        return null;
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (!empty($parts['port']) ? ':' . $parts['port'] : '');
    }
}