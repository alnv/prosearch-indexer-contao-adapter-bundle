<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndexDocument;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;

class DocumentPayloadBuilder
{
    public function build(array $row, ?array $chunks = null): array
    {
        // normalized keys: the proxy filters categories case-sensitively (keyword field)
        $types = array_values(array_unique(array_filter(array_map([Categories::class, 'normalize'], IndicesRepository::deserialize($row['types'] ?? null)), static fn(string $type): bool => '' !== $type)));
        $images = IndicesRepository::deserialize($row['images'] ?? null);
        $headings = @unserialize((string)($row['headings'] ?? ''), ['allowed_classes' => false]);
        $headings = (\is_array($headings) ? $headings : []) + IndexDocument::emptyHeadings();

        $document = [
            'id' => (string)$row['id'],
            'title' => (string)($row['title'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'url' => (string)($row['url'] ?? ''),
            'domain' => (string)($row['domain'] ?? ''),
            'language' => (string)($row['language'] ?? ''),
            'types' => $types,
            'image' => (string)($images[0] ?? ''),
            'text' => (string)($row['text'] ?? ''),
            'strong' => implode(', ', IndicesRepository::deserialize($row['strong'] ?? null)),
        ];

        foreach (IndexDocument::emptyHeadings() as $level => $ignored) {
            $document[$level] = implode(', ', array_map('strval', (array)($headings[$level] ?? [])));
        }

        if (null !== $chunks) {
            $document['chunks'] = $chunks;
        }

        return $document;
    }
}
