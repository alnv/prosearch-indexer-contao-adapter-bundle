<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Contao\System;

class MappingFactory
{
    private const array ANALYZERS = [
        'german' => ['type' => 'custom', 'tokenizer' => 'standard', 'filter' => ['lowercase', 'german_stopwords', 'german_normalization', 'german_light_stemmer']],
        'english' => ['type' => 'custom', 'tokenizer' => 'standard', 'filter' => ['english_possessive_stemmer', 'lowercase', 'english_stopwords', 'english_light_stemmer']],
        'french' => ['type' => 'custom', 'tokenizer' => 'standard', 'filter' => ['french_elision', 'lowercase', 'french_stopwords', 'french_light_stemmer']],
        'contao' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'filter' => ['lowercase']],
    ];

    public function getAnalyzers(): array
    {
        return self::ANALYZERS;
    }

    public function getChunkMapping(string $analyzer = 'standard'): array
    {
        return [
            'type' => 'nested',
            'properties' => [
                'text' => ['type' => 'text', 'analyzer' => $analyzer ?: 'standard'],
                'position' => ['type' => 'integer'],
                'token_count' => ['type' => 'integer', 'index' => false],
            ],
        ];
    }

    public function create(string $index, array $options = [], ?object $hookContext = null, bool $chunks = false, bool $vectors = false): array
    {
        $analyzer = (string)(($options['analyzer'] ?? '') ?: 'standard');
        $analyzers = self::ANALYZERS;
        $analyzers['autocomplete'] = ['filter' => ['lowercase', 'autocomplete'], 'type' => 'custom', 'tokenizer' => 'standard'];

        $text = static fn(bool $autocomplete): array => ['type' => 'text', 'analyzer' => $analyzer] + ($autocomplete ? ['copy_to' => ['autocomplete']] : []);

        $params = [
            'index' => $index,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => $analyzers,
                        'char_filter' => [
                            'german_mapping' => [
                                'type' => 'mapping',
                                'mappings' => ['ä => ae', 'ö => oe', 'ü => ue', 'Ä => Ae', 'Ö => Oe', 'Ü => Ue', 'ß => ss', '- => '],
                            ],
                            'hyphen_to_space' => ['type' => 'mapping', 'mappings' => ['- => ']],
                        ],
                        'filter' => [
                            'autocomplete' => ['max_shingle_size' => 4, 'min_shingle_size' => 2, 'type' => 'shingle'],
                            'decompound_filter' => [
                                'type' => 'word_delimiter',
                                'preserve_original' => true,
                                'split_on_numerics' => false,
                                'split_on_case_change' => true,
                                'generate_word_parts' => true,
                                'generate_number_parts' => false,
                                'catenate_words' => true,
                                'catenate_numbers' => false,
                                'catenate_all' => false,
                            ],
                            'english_stemmer' => ['type' => 'stemmer', 'language' => 'english'],
                            'german_stemmer' => ['type' => 'stemmer', 'language' => 'german'],
                            'french_stemmer' => ['type' => 'stemmer', 'language' => 'french'],
                            'english_stopwords' => ['type' => 'stop', 'stopwords' => ['_english_']],
                            'french_stopwords' => ['type' => 'stop', 'stopwords' => ['_french_']],
                            'german_stopwords' => ['type' => 'stop', 'stopwords' => ['_german_']],
                            'german_light_stemmer' => ['type' => 'stemmer', 'language' => 'light_german'],
                            'english_light_stemmer' => ['type' => 'stemmer', 'language' => 'light_english'],
                            'english_possessive_stemmer' => ['type' => 'stemmer', 'language' => 'possessive_english'],
                            'french_light_stemmer' => ['type' => 'stemmer', 'language' => 'light_french'],
                            'french_elision' => ['type' => 'elision', 'articles_case' => true, 'articles' => ['l', 'm', 't', 'qu', 'n', 's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisqu']],
                            'custom_asciifolding' => ['type' => 'asciifolding', 'preserve_original' => true],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'autocomplete' => ['type' => 'text', 'fielddata' => true, 'analyzer' => 'autocomplete'],
                        'title' => $text(true),
                        'description' => $text(true),
                        'text' => $text(false),
                        'document' => $text(false),
                        'h1' => $text(true),
                        'h2' => $text(true),
                        'h3' => $text(true),
                        'h4' => $text(false),
                        'h5' => $text(false),
                        'h6' => $text(false),
                        'strong' => $text(false),
                        // categories: exact values for the filter (dynamic mapping made it a text field)
                        'types' => ['type' => 'keyword'],
                        'language' => ['type' => 'keyword'],
                        'domain' => ['type' => 'keyword'],
                        'url' => ['type' => 'keyword'],
                        'image' => ['type' => 'keyword', 'index' => false],
                    ],
                    '_meta' => ['prosearch' => ['schema' => 2]],
                ],
            ],
        ];

        if ($chunks) {
            $params['body']['mappings']['properties']['chunks'] = $this->getChunkMapping($analyzer);
        }

        if ($chunks && $vectors) {
            $params['body']['settings']['index'] = ['knn' => true];
        }

        if (isset($GLOBALS['TL_HOOKS']['psCreateMapping']) && \is_array($GLOBALS['TL_HOOKS']['psCreateMapping'])) {
            foreach ($GLOBALS['TL_HOOKS']['psCreateMapping'] as $callback) {
                if (\is_array($callback) && isset($callback[0], $callback[1])) {
                    System::importStatic($callback[0])->{$callback[1]}($params, $options, $hookContext ?? $this);
                }
            }
        }

        return $params;
    }
}