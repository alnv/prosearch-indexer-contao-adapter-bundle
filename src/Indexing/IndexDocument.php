<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing;

final class IndexDocument
{
    public const string TYPE_PAGE = 'page';
    public const string TYPE_FILE = 'file';

    /**
     * @param list<string> $images file UUIDs or paths (first one = result image)
     * @param list<string> $types search categories
     * @param list<string> $strong highlighted texts
     * @param array<string, list<string>> $headings h1 … h6
     * @param list<int|string> $groups member groups of protected pages
     */
    public function __construct(
        public readonly string  $url,
        public readonly string  $domain,
        public readonly string  $docType,
        public readonly string  $language,
        public readonly int     $pageId,
        public readonly int     $rootId,
        public readonly string  $title,
        public readonly string  $description = '',
        public readonly array   $images = [],
        public readonly array   $types = [],
        public readonly ?string $text = '',
        public readonly array   $strong = [],
        public readonly array   $headings = [],
        public readonly string  $originUrl = '',
        public readonly bool    $protected = false,
        public readonly array   $groups = [],
        public readonly string  $sourceHash = '',
    )
    {
    }

    public static function urlHash(string $url): string
    {
        return sha1($url);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function emptyHeadings(): array
    {
        return ['h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []];
    }

    /**
     * Metadata that is transferred to the search index (the text is represented by its hash).
     *
     * @return array<string, mixed>
     */
    public function metadataForChecksum(): array
    {
        return [
            'url' => $this->url,
            'domain' => $this->domain,
            'doc_type' => $this->docType,
            'language' => $this->language,
            'pageId' => $this->pageId,
            'rootId' => $this->rootId,
            'title' => $this->title,
            'description' => $this->description,
            'images' => array_values($this->images),
            'types' => array_values($this->types),
            'strong' => array_values($this->strong),
            'headings' => $this->headings + self::emptyHeadings(),
            'origin_url' => $this->originUrl,
            'protected' => $this->protected,
            'groups' => array_values(array_map('strval', $this->groups)),
        ];
    }

    public static function checksum(array $metadata, string $contentHash): string
    {
        return hash('sha256', json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\0" . $contentHash);
    }
}
