<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\Queue\DocumentPayloadBuilder;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\StringUtil;

class DocumentFactory
{
    public function __construct(private readonly ?DocumentPayloadBuilder $payloadBuilder = null)
    {
    }

    /**
     * @return array<string, mixed>|false
     */
    public function create(int|string $indicesId): array|false
    {
        $indices = IndicesModel::findByPk($indicesId);

        if (!$indices) {
            return false;
        }

        $row = $indices->row();

        if (\array_key_exists('text', $row) && null !== $row['text']) {
            $document = ($this->payloadBuilder ?? new DocumentPayloadBuilder())->build($row);
            $document['id'] = $indicesId;

            return $document;
        }

        $document = [
            'id' => $indicesId,
            'title' => $indices->title ?: '',
            'description' => $indices->description ?: '',
            'url' => $indices->url,
            'domain' => $indices->domain,
            'language' => $indices->language,
        ];

        $types = StringUtil::deserialize($indices->types, true);
        $document['types'] = array_filter($types, static fn($type) => strtolower((string)$type));

        foreach (StringUtil::deserialize($row['document'] ?? null, true) as $field => $values) {
            if (\is_array($values)) {
                $values = implode(', ', $values);
            }

            $document[$field] = $values;
        }

        return $document;
    }
}