<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Collector;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Logger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction\HtmlTextExtractor;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Extraction\TextNormalizer;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndexDocument;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SaveResult;
use Contao\CoreBundle\Search\Document;
use Contao\CoreBundle\Search\Indexer\IndexerException;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Validator;
use Psr\Log\LogLevel;
use Symfony\Component\DomCrawler\Crawler;

class PageDocumentCollector
{
    private array $imageCache = [];

    public function __construct(
        private readonly IndicesRepository $repository,
        private readonly HtmlTextExtractor $extractor,
        private readonly int               $maxTextBytes = 1000000,
    )
    {
    }

    public function collect(Document $document, array $meta): SaveResult
    {
        $crawler = $document->getContentCrawler();
        $url = self::normalizeUrl($document->getUri()->__toString());

        $canonical = $crawler->filterXPath("//link[@rel='canonical']")->extract(['href'])[0] ?? '';

        if ('' !== $canonical) {
            $canonical = self::normalizeUrl($canonical);

            if (Validator::isUrl($canonical) && $url !== $canonical) {
                throw IndexerException::createAsWarning('Cannot index canonical page');
            }
        }

        $page = PageModel::findById((int)($meta['pageId'] ?? 0));

        if (null === $page) {
            throw IndexerException::createAsWarning('The page ' . (int)($meta['pageId'] ?? 0) . ' does not exist.');
        }

        $page->loadDetails();

        $language = (string)($meta['language'] ?? '') ?: self::language($crawler);
        $text = $this->extractor->extract($document->getBody());

        if (\strlen($text) > $this->maxTextBytes) {
            Logger::set(\sprintf('ProSearch: the text of %s was shortened to %d bytes (indexing.max_text_bytes).', $url, $this->maxTextBytes), LogLevel::INFO, __METHOD__);
            $text = TextNormalizer::truncate($text, $this->maxTextBytes);
        }

        $headings = [];

        foreach (array_keys(IndexDocument::emptyHeadings()) as $level) {
            $headings[$level] = self::texts($crawler, 'body ' . $level);
        }

        $indexDocument = new IndexDocument(
            url: $url,
            domain: $document->getUri()->getHost(),
            docType: IndexDocument::TYPE_PAGE,
            language: $language,
            pageId: (int)$page->id,
            rootId: (int)$page->rootId,
            title: $this->title($crawler, $headings['h1']),
            description: $this->description($crawler),
            images: $this->images($crawler),
            types: self::searchTypes($crawler),
            text: $text,
            strong: self::texts($crawler, 'body main strong'),
            headings: $headings,
            protected: (bool)($meta['protected'] ?? false),
            groups: array_values(array_map('strval', (array)($meta['groups'] ?? []))),
        );

        return $this->repository->save($indexDocument);
    }

    public static function normalizeUrl(string $url): string
    {
        $url = StringUtil::decodeEntities($url);

        return (string)strtok($url, '?#');
    }

    public static function language(Crawler $crawler): string
    {
        try {
            return (string)($crawler->filterXPath('//html[@lang]')->first()->attr('lang') ?: 'en');
        } catch (\Throwable) {
            return 'en';
        }
    }

    /**
     * @return list<string>
     */
    public static function searchTypes(Crawler $crawler): array
    {
        $types = [];

        foreach ($crawler->filterXPath("//meta[@name='search:type']")->extract(['content']) as $value) {
            foreach (Categories::normalizeList((string)$value) as $type) {
                if (!\in_array($type, $types, true)) {
                    $types[] = $type;
                }
            }
        }

        return $types;
    }

    public static function texts(Crawler $crawler, string $selector): array
    {
        $texts = [];

        foreach ($crawler->filter($selector) as $node) {
            $text = TextNormalizer::singleLine((string)$node->textContent);

            if ('' !== $text && !\in_array($text, $texts, true)) {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    private function title(Crawler $crawler, array $h1): string
    {
        foreach (["//meta[@property='og:title']/@content", '//head/title'] as $xpath) {
            $nodes = $crawler->filterXPath($xpath);

            if ($nodes->count() > 0 && '' !== ($title = TextNormalizer::singleLine($nodes->first()->text('', true)))) {
                return $title;
            }
        }

        return $h1[0] ?? '';
    }

    private function description(Crawler $crawler): string
    {
        foreach (["//meta[@property='og:description']/@content", "//meta[@name='description']/@content"] as $xpath) {
            $nodes = $crawler->filterXPath($xpath);

            if ($nodes->count() > 0 && '' !== ($description = TextNormalizer::singleLine($nodes->first()->text('', true)))) {
                return $description;
            }
        }

        return '';
    }

    private function images(Crawler $crawler): array
    {
        $images = [];

        foreach ($crawler->filterXPath("//meta[@property='og:image' or @property='og:image:url']/@content") as $node) {
            $path = ltrim((string)parse_url(trim((string)$node->nodeValue), PHP_URL_PATH), '/');

            if ('' === $path) {
                continue;
            }

            $path = rawurldecode($path);

            if (!isset($this->imageCache[$path])) {
                $file = FilesModel::findByPath($path);
                $this->imageCache[$path] = $file ? StringUtil::binToUuid($file->uuid) : $path;
            }

            if (!\in_array($this->imageCache[$path], $images, true)) {
                $images[] = $this->imageCache[$path];
            }
        }

        return $images;
    }
}