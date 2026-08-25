<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Logger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use OpenSearch\ClientBuilder;
use Psr\Log\LogLevel;

class Opensearch extends AbstractAdapter
{
    public const string INDEX = 'contao_search';

    protected string $strSignature = "";

    protected array|bool $arrCredentials = [];

    protected array $arrAnalyzer = [
        "german" => [
            "type" => "custom",
            "tokenizer" => "whitespace",
            "filter" => ["lowercase", "german_stopwords", "german_stemmer"]
        ],
        "english" => [
            "type" => "custom",
            "tokenizer" => "whitespace",
            "filter" => ["lowercase", "english_stopwords", "english_stemmer"]
        ],
        "french" => [
            "type" => "custom",
            "tokenizer" => "whitespace",
            "filter" => ["lowercase", "french_stopwords", "french_stemmer"]
        ],
        "contao" => [
            "type" => "custom",
            "tokenizer" => "whitespace",
            "filter" => ["lowercase"]
        ]
    ];

    public function connect(): void
    {
        $this->arrCredentials = (new Credentials())->getCredentials();
        if ($this->arrCredentials === false) {
            return;
        }

        $this->strSignature = $this->arrCredentials['signature'] ?? '';
        switch ($this->arrCredentials['type']) {
            case 'opensearch':
                try {
                    $this->objClient = ClientBuilder::create()
                        ->setHosts([$this->arrCredentials['host'] . ($this->arrCredentials['port'] ? ':' . $this->arrCredentials['port'] : '')])
                        ->setBasicAuthentication($this->arrCredentials['username'], $this->arrCredentials['password'])
                        ->build();
                } catch (\Exception $objError) {
                    Logger::set($objError->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
                }
                break;
        }

        if (!$this->strSignature) {
            $this->objClient = null;
        }
    }

    public function getClient(): mixed
    {
        return $this->objClient;
    }

    public function deleteDatabases(): void
    {
        $this->connect();

        $objRoots = PageModel::findPublishedRootPages();
        if (!$objRoots) {
            return;
        }

        while ($objRoots->next()) {
            try {
                $strIndex = $this->getIndexName($objRoots->id);
                if (!$this->getClient()) {
                    if ((new Proxy($this->strLicense))->deleteDatabase($strIndex) === false) {
                        return;
                    }
                } else {
                    $this->deleteDatabase($strIndex);
                }
            } catch (\Exception $objError) {
                Logger::set($objError->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
            }
        }
    }

    public function deleteDatabase($strIndex): void
    {
        try {
            $params = [
                'index' => $strIndex
            ];

            $this->objClient->indices()->delete($params);
        } catch (\Exception $e) {
            throw new \Exception("Fehler beim Löschen des Index '{$strIndex}': " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function deleteIndex($strIndicesId): void
    {
        $this->connect();

        $strIndex = $this->getIndexName($this->getRootIdentifierFromIndicesId($strIndicesId));
        $objIndicesModel = IndicesModel::findByPk($strIndicesId);
        $objMicrodataModel = MicrodataModel::findByPid($strIndicesId);

        if (!$objIndicesModel) {
            return;
        }

        if (!$this->getClient()) {
            if ((new Proxy($this->strLicense))->deleteDocument($strIndex, $strIndicesId) === false) {
                return;
            }
        } else {
            $this->clientDelete($strIndex, $strIndicesId);
        }

        if ($objMicrodataModel) {
            while ($objMicrodataModel->next()) {
                $objMicrodataModel->delete();
            }
        }

        System::getContainer()
            ->get('monolog.logger.contao')
            ->log(LogLevel::DEBUG, 'Index (' . $strIndex . ') document with ID ' . $strIndicesId . ' was deleted.', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);

        $objIndicesModel->delete();
    }

    public function clientDelete($strIndex, $strIndicesId): void
    {
        $arrExists = $this->getClient()->exists(['index' => $strIndex, 'id' => $strIndicesId]);

        if ($arrExists === true || (is_array($arrExists) && ($arrExists['status'] ?? 404) === 200)) {
            $this->getClient()->deleteByQuery([
                'index' => $strIndex,
                'body' => [
                    'query' => [
                        'term' => [
                            'id' => $strIndicesId
                        ]
                    ]
                ]
            ]);
        }
    }

    public function getIndex($strIndicesId = null, int $intLimit = 5): array
    {
        $arrColumn = ['state=?'];
        $arrValue = [States::ACTIVE];

        if ($strIndicesId) {
            $arrValue[] = $strIndicesId;
            $arrColumn[] = 'id=?';
        }

        $objIndices = IndicesModel::findAll([
            'column' => $arrColumn,
            'value' => $arrValue,
            'limit' => $intLimit,
            'order' => 'last_indexed ASC'
        ]);

        if (!$objIndices) {
            return [];
        }

        $arrDocuments = [];
        while ($objIndices->next()) {
            $arrDocuments[$objIndices->url] = $this->createDocument($objIndices->id);
        }

        return $arrDocuments;
    }

    protected function createMapping(): void
    {
        $this->connect();

        $strAnalyzer = $this->arrOptions['analyzer'] ?? 'standard';
        $strIndex = $this->getIndexName($this->arrOptions['rootPageId'] ?? 0);

        $arrAnalyzer = $this->arrAnalyzer ?? [];

        $arrAnalyzer['autocomplete'] = [
            'tokenizer' => 'standard',
            'filter' => ['lowercase', 'autocomplete'],
            'type' => 'custom',
        ];

        $textMappingWithAutocomplete = [
            'type' => 'text',
            'analyzer' => $strAnalyzer,
            'copy_to' => ['autocomplete'],
        ];

        $textMappingStandard = [
            'type' => 'text',
            'analyzer' => $strAnalyzer,
        ];

        $keywordMapping = [
            'type' => 'keyword',
        ];

        $arrParams = [
            'index' => $strIndex,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => $arrAnalyzer,
                        'filter' => [
                            'autocomplete' => [
                                'type' => 'shingle',
                                'min_shingle_size' => 2,
                                'max_shingle_size' => 2,
                            ],
                            'english_stemmer' => [
                                "type" => "stemmer",
                                "language" => "english"
                            ],
                            'german_stemmer' => [
                                "type" => "stemmer",
                                "language" => "german"
                            ],
                            'french_stemmer' => [
                                "type" => "stemmer",
                                "language" => "french"
                            ],
                            'english_stopwords' => [
                                "type" => "stop",
                                "stopwords" => ["_english_"]
                            ],
                            'french_stopwords' => [
                                "type" => "stop",
                                "stopwords" => ["_french_"]
                            ],
                            'german_stopwords' => [
                                "type" => "stop",
                                "stopwords" => ["_german_"]
                            ]
                        ]
                    ]
                ],
                'mappings' => [
                    'properties' => [
                        'autocomplete' => [
                            'type' => 'text',
                            'analyzer' => 'autocomplete'
                        ],
                        'title' => $textMappingWithAutocomplete,
                        'description' => $textMappingWithAutocomplete,
                        'h1' => $textMappingWithAutocomplete,
                        'h2' => $textMappingStandard,
                        'h3' => $textMappingStandard,
                        'text' => $textMappingStandard,
                        'document' => $textMappingStandard,
                        'h4' => $textMappingStandard,
                        'h5' => $textMappingStandard,
                        'h6' => $textMappingStandard,
                        'strong' => $textMappingStandard,
                        'language' => $keywordMapping,
                        'domain' => $keywordMapping,
                        'url' => $keywordMapping,
                    ],
                ],
            ],
        ];

        if (!empty($GLOBALS['TL_HOOKS']['psCreateMapping']) && is_array($GLOBALS['TL_HOOKS']['psCreateMapping'])) {
            foreach ($GLOBALS['TL_HOOKS']['psCreateMapping'] as $callback) {
                if (is_array($callback) && isset($callback[0], $callback[1])) {
                    System::importStatic($callback[0])->{$callback[1]}($arrParams, $this->arrOptions, $this);
                }
            }
        }

        if (!$this->getClient()) {
            (new Proxy($this->strLicense))->indexMapping($arrParams);
        } else {
            $this->clientMapping($arrParams);
        }
    }

    public function clientMapping($arrParams): void
    {
        if (!$this->getClient()) {
            return;
        }

        $arrExists = $this->getClient()->indices()->exists([
            "index" => $arrParams['index']
        ]);

        $blnExists = ($arrExists === true || (is_array($arrExists) && ($arrExists['status'] ?? 404) === 200));

        if (!$blnExists) {
            $this->getClient()->indices()->create($arrParams);

            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::DEBUG, 'Mapping for Index ' . $arrParams['index'] . ' was created.', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);
        }
    }

    public function indexByDocument($arrDocument): void
    {
        $objIndicesModel = IndicesModel::findByPk($arrDocument['id']);
        if (!$objIndicesModel) {
            return;
        }

        $strIndex = $this->getIndexName($this->getRootIdentifierFromIndicesId($arrDocument['id']));

        $arrParams = [
            'index' => $strIndex,
            'id' => $arrDocument['id'],
            'body' => $arrDocument
        ];

        if (!$this->getClient()) {
            if ((new Proxy($this->strLicense))->indexDocument($arrParams) === false) {
                return;
            }
        } else {
            try {
                $this->clientIndex($arrParams);
            } catch (\Exception $e) {
                Logger::set($e->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
                return;
            }
        }

        $objIndicesModel->last_indexed = time();
        $objIndicesModel->save();
    }

    public function clientIndex($arrParams): void
    {
        if (!$this->getClient()) {
            return;
        }

        try {
            $arrExists = $this->getClient()->exists(['index' => $arrParams['index'], 'id' => $arrParams['id']]);
            $blnExists = ($arrExists === true || (is_array($arrExists) && ($arrExists['status'] ?? 404) === 200));

            if ($blnExists) {
                unset($arrParams['body']['id']);
                $this->getClient()->update([
                    'index' => $arrParams['index'],
                    'id' => $arrParams['id'],
                    'body' => [
                        'doc' => $arrParams['body']
                    ]
                ]);
                System::getContainer()
                    ->get('monolog.logger.contao')
                    ->log(LogLevel::DEBUG, 'Index (' . $arrParams['index'] . ') document with ID ' . $arrParams['id'] . ' was updated.', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);
            } else {
                $this->getClient()->index($arrParams);
                System::getContainer()
                    ->get('monolog.logger.contao')
                    ->log(LogLevel::DEBUG, 'Index (' . $arrParams['index'] . ') document with ID ' . $arrParams['id'] . ' was created.', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);
            }
        } catch (\Exception $objError) {
            Logger::set($objError->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
            return;
        }
    }

    public function indexDocuments($strIndicesId): void
    {
        if (!$strIndicesId) {
            return;
        }

        $this->connect();
        $arrDocuments = $this->getIndex($strIndicesId);
        $this->createMapping();

        foreach ($arrDocuments as $arrDocument) {
            $this->indexByDocument($arrDocument);
        }
    }

    public function createDocument($strIndicesId): bool|array
    {
        $objIndices = IndicesModel::findByPk($strIndicesId);

        if (!$objIndices) {
            return false;
        }

        $arrDomDocument = StringUtil::deserialize($objIndices->document, true);

        $arrDocument = [
            'id' => $strIndicesId,
            'title' => $objIndices->title ?: '',
            'description' => $objIndices->description ?: '',
            'url' => $objIndices->url,
            'domain' => $objIndices->domain,
            'language' => $objIndices->language
        ];

        $arrTypes = StringUtil::deserialize($objIndices->types, true);
        $objMicroData = MicrodataModel::findByPid($strIndicesId);

        if ($objMicroData) {
            while ($objMicroData->next()) {
                if ($objMicroData->type && !in_array($objMicroData->type, $arrTypes)) {
                    $arrTypes[] = $objMicroData->type;
                }
            }
        }

        $arrDocument['types'] = array_filter($arrTypes, function ($strType) {
            return strtolower($strType);
        });

        foreach ($arrDomDocument as $strField => $varValues) {
            if (is_array($varValues)) {
                $varValues = implode(', ', $varValues);
            }
            $arrDocument[$strField] = $varValues;
        }

        $objIndices->last_indexed = time();
        $objIndices->save();

        return $arrDocument;
    }

    public function autoCompilation($arrKeywords, string $strIndexName = ''): array
    {
        $arrResults = [
            'hits' => [],
            'didYouMean' => []
        ];

        $strRootPageId = $this->arrOptions['rootPageId'] ?? 0;
        $strAnalyzer = $this->arrOptions['analyzer'] ?? 'standard';

        if (!$strIndexName) {
            $strIndexName = $this->getIndexName($strRootPageId);
        }

        $strQuery = $arrKeywords['query'] ?? '';

        $params = [
            'index' => $strIndexName,
            'body' => [
                'size' => 0,
                'aggs' => [
                    'autocomplete' => [
                        'terms' => [
                            'field' => 'autocomplete.keyword',
                            'order' => [
                                '_count' => 'desc'
                            ],
                            'include' => $strQuery . '.*'
                        ]
                    ]
                ],
                'query' => [
                    'prefix' => [
                        'autocomplete' => [
                            'value' => $strQuery
                        ]
                    ]
                ],
                'suggest' => [
                    'didYouMean' => [
                        'text' => $strQuery,
                        'phrase' => [
                            'field' => 'autocomplete',
                            'size' => 1,
                            'gram_size' => 3,
                            'analyzer' => $strAnalyzer,
                            'direct_generator' => [
                                [
                                    'field' => 'autocomplete',
                                    'suggest_mode' => 'always'
                                ]
                            ],
                            'highlight' => [
                                'pre_tag' => '<em>',
                                'post_tag' => '</em>'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        if (!empty($GLOBALS['TL_HOOKS']['psAutoCompilation']) && is_array($GLOBALS['TL_HOOKS']['psAutoCompilation'])) {
            foreach ($GLOBALS['TL_HOOKS']['psAutoCompilation'] as $callback) {
                if (is_array($callback) && isset($callback[0], $callback[1])) {
                    System::importStatic($callback[0])->{$callback[1]}($params, $arrKeywords, $this->arrOptions, $this);
                }
            }
        }

        $response = $this->getClient()->search($params);

        $arrBuckets = $response['aggregations']['autocomplete']['buckets'] ?? [];
        foreach ($arrBuckets as $arrBucket) {
            $arrResults['hits'][] = [
                'term' => $arrBucket['key'],
                'template' => $arrBucket['key']
            ];
        }

        $arrSuggests = $response['suggest']['didYouMean'] ?? [];
        foreach ($arrSuggests as $arrSuggest) {
            if (!empty($arrSuggest['options']) && is_array($arrSuggest['options'])) {
                foreach ($arrSuggest['options'] as $arrOption) {
                    $arrResults['didYouMean'][] = $arrOption['text'];
                }
            }
        }

        return $arrResults;
    }

    public function search($arrKeywords, string $strIndexName = '', int $intTryCounts = 0): array
    {
        $arrResults = [
            'hits' => [],
            'didYouMean' => []
        ];

        $strRootPageId = $this->arrOptions['rootPageId'] ?? 0;
        $strAnalyzer = $this->arrOptions['analyzer'] ?? 'standard';

        if (!$strIndexName) {
            $strIndexName = $this->getIndexName($strRootPageId);
        }

        $intSize = $this->getSizeValue();
        $strQuery = $arrKeywords['query'] ?? '';

        $allSearchFields = ['description', 'text', 'document', 'title', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong'];
        $boostedFields = ['title^5', 'h1^3', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong'];

        $params = [
            'index' => $strIndexName,
            'body' => [
                'size' => $intSize,
                'query' => [
                    'bool' => []
                ],
                'sort' => [
                    ['_score' => 'desc'],
                    ['url' => 'asc']
                ],
                'highlight' => [
                    'pre_tags' => '<strong>',
                    'post_tags' => '</strong>',
                    'fields' => [
                        'text' => new \stdClass(),
                        'document' => new \stdClass()
                    ],
                    'require_field_match' => true,
                    'type' => 'plain',
                    'fragment_size' => 150,
                    'number_of_fragments' => 3,
                    'fragmenter' => 'span'
                ],
                'aggs' => [
                    'autocomplete' => [
                        'terms' => [
                            'field' => 'autocomplete.keyword',
                            'order' => [
                                '_count' => 'desc'
                            ],
                            'include' => $strQuery . '.*'
                        ]
                    ]
                ],
                'suggest' => [
                    'didYouMean' => [
                        'text' => $strQuery,
                        'phrase' => [
                            'field' => 'autocomplete',
                            'size' => 1,
                            'gram_size' => 3,
                            'analyzer' => $strAnalyzer,
                            'direct_generator' => [
                                [
                                    'field' => 'autocomplete',
                                    'suggest_mode' => 'always'
                                ]
                            ],
                            'highlight' => [
                                'pre_tag' => '<em>',
                                'post_tag' => '</em>'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        if (!empty($this->arrOptions['search_after'])) {
            $params['body']['search_after'] = explode(',', $this->arrOptions['search_after']);
        }

        if ($strQuery !== '') {
            switch ($intTryCounts) {
                case 0:
                    $params['body']['query']['bool'] = [
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => $strQuery,
                                    'analyzer' => $strAnalyzer,
                                    'type' => 'phrase_prefix',
                                    'slop' => 1,
                                    'fields' => $allSearchFields
                                ]
                            ]
                        ],
                        'should' => [
                            [
                                'multi_match' => [
                                    'query' => $strQuery,
                                    'analyzer' => $strAnalyzer,
                                    'type' => 'phrase_prefix',
                                    'fields' => $boostedFields
                                ]
                            ]
                        ]
                    ];
                    break;

                case 1:
                    $params['body']['query']['bool'] = [
                        'must' => [
                            [
                                'query_string' => [
                                    'query' => '*' . $strQuery . '*',
                                    'allow_leading_wildcard' => true,
                                    'analyze_wildcard' => true,
                                    'phrase_slop' => 1,
                                    'fields' => $allSearchFields
                                ]
                            ]
                        ],
                        'should' => [
                            [
                                'multi_match' => [
                                    'query' => $strQuery,
                                    'analyzer' => $strAnalyzer,
                                    'type' => 'best_fields',
                                    'slop' => 1,
                                    'fields' => $boostedFields
                                ]
                            ]
                        ]
                    ];
                    break;

                case 2:
                    $params['body']['query']['bool'] = [
                        'must' => [
                            [
                                'query_string' => [
                                    'query' => '*' . $strQuery . '*',
                                    'allow_leading_wildcard' => true,
                                    'analyze_wildcard' => true,
                                    'fuzziness' => 'AUTO',
                                    'phrase_slop' => 1,
                                    'fields' => $allSearchFields
                                ]
                            ]
                        ],
                        'should' => [
                            [
                                'multi_match' => [
                                    'query' => $strQuery,
                                    'analyzer' => $strAnalyzer,
                                    'type' => 'best_fields',
                                    'fuzziness' => 'AUTO',
                                    'slop' => 1,
                                    'fields' => $boostedFields
                                ]
                            ]
                        ]
                    ];
                    break;
            }
        }

        if (!empty($this->arrOptions['language'])) {
            $params['body']['query']['bool']['filter'][] = [
                'term' => ['language' => $this->arrOptions['language']]
            ];
        }

        if (!empty($this->arrOptions['domain'])) {
            $params['body']['query']['bool']['filter'][] = [
                'terms' => ['domain' => explode(',', str_replace(' ', '', $this->arrOptions['domain']))]
            ];
        }

        if (!empty($arrKeywords['types']) && is_array($arrKeywords['types'])) {
            $params['body']['query']['bool']['filter'][] = [
                'terms' => ['types' => array_map('strtolower', $arrKeywords['types'])]
            ];
        }

        if (empty($params['body']['query'])) {
            return $arrResults;
        }

        if (!empty($GLOBALS['TL_HOOKS']['psSearchQuery']) && is_array($GLOBALS['TL_HOOKS']['psSearchQuery'])) {
            foreach ($GLOBALS['TL_HOOKS']['psSearchQuery'] as $callback) {
                if (is_array($callback) && isset($callback[0], $callback[1])) {
                    System::importStatic($callback[0])->{$callback[1]}($params, $arrKeywords, $intTryCounts, $this->arrOptions, $this);
                }
            }
        }

        $response = $this->getClient()->search($params);

        $arrResults['tries'] = $intTryCounts;
        $arrResults['hits'] = $response['hits']['hits'] ?? [];
        $arrResults['max_score'] = $response['hits']['max_score'] ?? 0;

        foreach (($response['suggest']['didYouMean'] ?? []) as $arrSuggest) {
            if (!empty($arrSuggest['options']) && is_array($arrSuggest['options'])) {
                foreach ($arrSuggest['options'] as $arrOption) {
                    $arrResults['didYouMean'][] = $arrOption['text'];
                }
            }
        }

        $arrBuckets = $response['aggregations']['autocomplete']['buckets'] ?? [];
        foreach ($arrBuckets as $arrBucket) {
            $arrResults['autocomplete'][] = [
                'term' => $arrBucket['key'],
                'template' => $arrBucket['key']
            ];
        }

        $intMaxTryCounts = (isset($this->arrOptions['fuzzy']) && $this->arrOptions['fuzzy'] === true) ? 2 : 1;
        if (empty($arrResults['hits']) && $intMaxTryCounts > $intTryCounts) {
            return $this->search($arrKeywords, $strIndexName, $intTryCounts + 1);
        }

        return $arrResults;
    }

    public function getAnalyzer(): array
    {
        return $this->arrAnalyzer;
    }

    protected function getSizeValue()
    {
        return $this->arrOptions['perPage'] ?: 500;
    }

    public function getLicense(): string
    {
        return $this->strLicense;
    }
}