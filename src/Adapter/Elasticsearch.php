<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Authorization;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Logger;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Environment;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Psr\Log\LogLevel;

// https://github.com/elastic/elasticsearch-php
class Elasticsearch extends AbstractAdapter
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
            case 'elasticsearch':
                try {
                    $this->objClient = ClientBuilder::create()
                        ->setHosts([$this->arrCredentials['host'] . ($this->arrCredentials['port'] ? ':' . $this->arrCredentials['port'] : '')])
                        ->setBasicAuthentication($this->arrCredentials['username'], $this->arrCredentials['password'])
                        ->setCABundle($this->arrCredentials['cert'])
                        ->build();
                } catch (\Exception $objError) {
                    Logger::set($objError->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
                }
                break;
            case 'elasticsearch_cloud':
                try {
                    $this->objClient = ClientBuilder::create()
                        ->setHosts([$this->arrCredentials['host']])
                        ->setApiKey($this->arrCredentials['key'])
                        ->build();
                } catch (\Exception $objError) {
                    Logger::set($objError->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
                }
                break;
            case 'licence':
                $objAuthorization = new Authorization();
                $strDomain = Environment::get('httpHost');
                $arrLicenseKeys = StringUtil::deserialize($this->arrCredentials['keys'], true);

                if (empty($arrLicenseKeys)) {
                    $strLicense = $this->arrCredentials['key'] ?? '';
                } else {
                    $strLicense = $objAuthorization->pluckKeyFromKeysGlobalByDomain($arrLicenseKeys, $strDomain);
                }

                $this->strLicense = $objAuthorization->encodeLicense($strLicense, $strDomain, ($this->arrCredentials['authToken'] ?? ''));
                return;
        }

        if (!$this->strSignature) {
            $this->objClient = null;
        }
    }

    public function getClient(): Client|null
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
        $objCurl = \curl_init();

        \curl_setopt($objCurl, CURLOPT_URL, "http://" . ($this->arrCredentials['host'] ?? '') . ":" . ($this->arrCredentials['port'] ?? '') . "/" . $strIndex);
        \curl_setopt($objCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');

        if ($this->arrCredentials['username'] && $this->arrCredentials['password']) {
            \curl_setopt($objCurl, CURLOPT_HTTPHEADER, array('Authorization:Basic ' . base64_encode($this->arrCredentials['username'] . ':' . $this->arrCredentials['password'])));
        }

        \curl_exec($objCurl);
        \curl_close($objCurl);
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
        if ($this->getClient()->exists(['index' => $strIndex, 'id' => $strIndicesId])->asBool()) {
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

    protected function createMapping($rootId = null): void
    {
        $this->connect();

        $strAnalyzer = $this->arrOptions['analyzer'];
        $strIndex = $this->getIndexName($rootId ?: $this->arrOptions['rootPageId']);
        $arrAnalyzer = $this->arrAnalyzer;

        $arrAnalyzer["autocomplete"] = [
            "filter" => ["lowercase", "autocomplete"],
            "type" => "custom",
            "tokenizer" => "standard"
        ];

        $arrParams = [
            "index" => $strIndex,
            "body" => [
                "settings" => [
                    "number_of_shards" => 1,
                    "number_of_replicas" => 0,
                    "analysis" => [
                        "analyzer" => $arrAnalyzer,
                        "char_filter" => [
                            "german_mapping" => [
                                "type" => "mapping",
                                "mappings" => [
                                    "ä => ae",
                                    "ö => oe",
                                    "ü => ue",
                                    "Ä => Ae",
                                    "Ö => Oe",
                                    "Ü => Ue",
                                    "ß => ss",
                                    "- => "
                                ]
                            ],
                            "hyphen_to_space" => [
                                "type" => "mapping",
                                "mappings" => [
                                    "- => "
                                ]
                            ]
                        ],
                        "filter" => [
                            "autocomplete" => [
                                "max_shingle_size" => 4,
                                "min_shingle_size" => 2,
                                "type" => "shingle"
                            ],
                            "decompound_filter" => [
                                "type" => "word_delimiter",
                                "preserve_original" => true,
                                "split_on_numerics" => false,
                                "split_on_case_change" => true,
                                "generate_word_parts" => true,
                                "generate_number_parts" => false,
                                "catenate_words" => true,
                                "catenate_numbers" => false,
                                "catenate_all" => false
                            ],
                            "english_stemmer" => [
                                "type" => "stemmer",
                                "language" => "english"
                            ],
                            "german_stemmer" => [
                                "type" => "stemmer",
                                "language" => "german"
                            ],
                            "french_stemmer" => [
                                "type" => "stemmer",
                                "language" => "french"
                            ],
                            "english_stopwords" => [
                                "type" => "stop",
                                "stopwords" => ["_english_"]
                            ],
                            "french_stopwords" => [
                                "type" => "stop",
                                "stopwords" => ["_french_"]
                            ],
                            "german_stopwords" => [
                                "type" => "stop",
                                "stopwords" => ["_german_"]
                            ],
                            "custom_asciifolding" => [
                                "type" => "asciifolding",
                                "preserve_original" => true
                            ]
                        ]
                    ]
                ],
                "mappings" => [
                    "properties" => [
                        "autocomplete" => [
                            "type" => "text",
                            "fielddata" => true,
                            "analyzer" => "autocomplete"
                        ],
                        "title" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                            "copy_to" => [
                                "autocomplete"
                            ]
                        ],
                        "description" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                            "copy_to" => [
                                "autocomplete"
                            ]
                        ],
                        "text" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer
                        ],
                        "document" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                        ],
                        "h1" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                            "copy_to" => [
                                "autocomplete"
                            ]
                        ],
                        "h2" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                            "copy_to" => [
                                "autocomplete"
                            ]
                        ],
                        "h3" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                            "copy_to" => [
                                "autocomplete"
                            ]
                        ],
                        "h4" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                        ],
                        "h5" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                        ],
                        "h6" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                        ],
                        "strong" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                        ],
                        "language" => [
                            "type" => "keyword"
                        ],
                        "domain" => [
                            "type" => "keyword"
                        ],
                        "url" => [
                            "type" => "keyword"
                        ]
                    ]
                ]
            ]
        ];

        if (isset($GLOBALS['TL_HOOKS']['psCreateMapping']) && is_array($GLOBALS['TL_HOOKS']['psCreateMapping'])) {
            foreach ($GLOBALS['TL_HOOKS']['psCreateMapping'] as $arrCallback) {
                System::importStatic($arrCallback[0])->{$arrCallback[1]}($arrParams, $this->arrOptions, $this);
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
        $blnExists = $this->getClient()
            ->indices()
            ->exists([
                "index" => $arrParams['index']
            ])
            ->asBool();

        if (!$blnExists) {
            $this->getClient()->indices()->create($arrParams);

            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::DEBUG, 'Mapping for Index ' . $arrParams['index'] . ' was created.', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);
        }
    }

    protected function indexByDocument($arrDocument): void
    {
        $objIndicesModel = IndicesModel::findByPk($arrDocument['id']);
        if (!$objIndicesModel) {
            return;
        }

        $strIndex = $this->getIndexName($this->getRootIdentifierFromIndicesId($objIndicesModel->id));

        $arrParams = [
            'index' => $strIndex,
            'id' => $arrDocument['id'],
            'body' => $arrDocument
        ];

        if (!$this->getClient()) {
            if (!(new Proxy($this->strLicense))->indexDocument($arrParams)) {
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

    public function clientIndex($arrParams): bool
    {
        if (!$this->getClient()) {
            throw new \RuntimeException('Client konnte nicht geladen werden.');
        }

        if ($this->getClient()->exists(['index' => $arrParams['index'], 'id' => $arrParams['id']])->asBool()) {
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

        return true;
    }

    public function indexDocuments($strIndicesId): void
    {
        if (!$strIndicesId) {
            return;
        }

        $this->connect();
        $level = 0;
        $arrDocuments = $this->getIndex($strIndicesId);

        foreach ($arrDocuments as $arrDocument) {
            if (!$level) {
                $rootId = $this->getRootIdentifierFromIndicesId($arrDocument['id']);
                $this->createMapping($rootId);
            }

            $this->indexByDocument($arrDocument);
            $level++;
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
                            'field' => 'autocomplete',
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