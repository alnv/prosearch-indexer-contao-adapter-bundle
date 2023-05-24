<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\System;
use Alnv\ProSearchIndexerContaoAdapterBundle\Entity\Result;
use Elastic\Elasticsearch\ClientBuilder;
use Psr\Log\LogLevel;

// https://github.com/elastic/elasticsearch-php
class Elasticsearch extends Adapter
{

    public const INDEX = 'contao_search';

    private string $strSignature = "";

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
        "contao" => [
            "type" => "custom",
            "tokenizer" => "whitespace",
            "filter" => ["lowercase"]
        ]
    ];

    public function connect()
    {

        $arrCredentials = (new Credentials())->getCredentials();

        if ($arrCredentials === false) {

            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::ERROR, 'No credentials for elasticsearch found', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_ERROR)]);

            return;
        }

        switch ($arrCredentials['type']) {
            case 'elasticsearch':
                try {
                    $this->objClient = ClientBuilder::create()
                        ->setHosts([$arrCredentials['host'] . ($arrCredentials['port'] ? ':' . $arrCredentials['port'] : '')])
                        ->setBasicAuthentication($arrCredentials['username'], $arrCredentials['password'])
                        ->setCABundle($arrCredentials['cert'])
                        ->build();
                } catch (\Exception $objError) {
                    System::getContainer()
                        ->get('monolog.logger.contao')
                        ->log(LogLevel::ERROR, $objError->getMessage(), ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_ERROR)]);
                }
                break;
            case 'elasticsearch_cloud':
                try {
                    $this->objClient = ClientBuilder::create()
                        ->setElasticCloudId($arrCredentials['cloudid'])
                        ->setApiKey($arrCredentials['key'])
                        ->build();
                } catch (\Exception $objError) {
                    System::getContainer()
                        ->get('monolog.logger.contao')
                        ->log(LogLevel::ERROR, $objError->getMessage(), ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_ERROR)]);
                    exit;
                }
                break;
            case 'licence':
                // todo
                break;
        }

        $this->strSignature = $arrCredentials['signature'] ?? '';

        if (!$this->strSignature) {
            $this->objClient = null;
        }

        if (!$this->objClient) {
            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::ERROR, 'No connection to the server could be established', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_ERROR)]);
        }
    }

    protected function rootPageSettings($strIndicesId): array
    {

        if ($objIndicesModel = IndicesModel::findByPk($strIndicesId)) {

            $objPage = \PageModel::findByPk($objIndicesModel->pageId);

            if ($objPage) {
                $objRootPage = \PageModel::findByPk($objPage->loadDetails()->rootId);
                return [
                    'rootId' => $objRootPage->id,
                    'analyzer' => $objRootPage->psAnalyzer ?: ''
                ];
            }
        }

        return [
            'rootId' => ''
        ];
    }

    protected function getIndexName($strRootId = ""): string
    {

        return Elasticsearch::INDEX . '_' . $this->strSignature . ($strRootId?'_'.$strRootId:'');
    }

    public function getClient()
    {

        return $this->objClient;
    }

    // "curl -X DELETE http://localhost:9200/contao_search"
    public function deleteIndex($strIndicesId): void
    {
        $this->connect();

        if (!$this->getClient()) {
            return;
        }

        $objIndicesModel = IndicesModel::findByPk($strIndicesId);

        if (!$objIndicesModel) {
            return;
        }

        $arrRootSettings = $this->rootPageSettings($strIndicesId);
        $strIndex = $this->getIndexName($arrRootSettings['rootId']);

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

            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::DEBUG, 'Index document with ID ' . $strIndicesId . ' was deleted.', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_CRON)]);
        }

        $objMicrodataModel = MicrodataModel::findByPid($strIndicesId);
        if ($objMicrodataModel) {
            while ($objMicrodataModel->next()) {
                $objMicrodataModel->delete();
            }
        }

        $objIndicesModel->delete();
    }

    /**
     * @param $strIndicesId
     * @param int $intLimit
     * @return array
     */
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

    /**
     * @return void
     * @throws \Elastic\Elasticsearch\Exception\ClientResponseException
     * @throws \Elastic\Elasticsearch\Exception\MissingParameterException
     * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
     */
    protected function createMapping($strIndicesId)
    {

        $this->connect();

        if (!$this->getClient()) {
            return;
        }

        $arrRootSettings = $this->rootPageSettings($strIndicesId);

        $strAnalyzer = $arrRootSettings['analyzer'] ?? 'contao';
        $strIndex = $this->getIndexName($arrRootSettings['rootId']);

        $arrAnalyzer = $this->arrAnalyzer;

        $arrAnalyzer["autocomplete"] = [
            "filter" => ["lowercase", "autocomplete"],
            "type" => "custom",
            "tokenizer" => "whitespace"
        ];

        $arrParams = [
            "index" => $strIndex,
            "body" => [
                "settings" => [
                    "analysis" => [
                        "analyzer" => $arrAnalyzer,
                        "filter" => [
                            "autocomplete" => [
                                "max_shingle_size" => 4,
                                "min_shingle_size" => 2,
                                "type" => "shingle"
                            ],
                            "english_stemmer" => [
                                "type" => "stemmer",
                                "language" => "english"
                            ],
                            "english_stopwords" => [
                                "type" => "stop",
                                "stopwords" => ["_english_"]
                            ],
                            "german_stemmer" => [
                                "type" => "stemmer",
                                "language" => "german"
                            ],
                            "german_stopwords" => [
                                "type" => "stop",
                                "stopwords" => ["_german_"]
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
                            "analyzer" => $strAnalyzer,
                            "copy_to" => [
                                "autocomplete"
                            ]
                        ],
                        "document" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                        ],
                        "h1" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                        ],
                        "h2" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
                        ],
                        "h3" => [
                            "type" => "text",
                            "analyzer" => $strAnalyzer,
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

        $blnExists = $this->getClient()->indices()->exists([
            "index" => $strIndex
        ])->asBool();

        if (!$blnExists) {
            $this->getClient()->indices()->create($arrParams);

            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::DEBUG, 'Mapping for Index ' . $strIndex . ' was created.', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_ACCESS)]);
        }
    }

    public function indexByDocument($arrDocument)
    {

        $objIndicesModel = IndicesModel::findByPk($arrDocument['id']);

        if (!$objIndicesModel) {
            return;
        }

        $arrRootSettings = $this->rootPageSettings($objIndicesModel->id);
        $strIndex = $this->getIndexName($arrRootSettings['rootId']);

        $arrParams = [
            'index' => $strIndex,
            'id' => $arrDocument['id'],
            'body' => $arrDocument
        ];

        try {

            if ($this->getClient()->exists(['index' => $strIndex, 'id' => $arrDocument['id']])->asBool()) {
                $this->getClient()->deleteByQuery([
                    'index' => $strIndex,
                    'body' => [
                        'query' => [
                            'term' => [
                                'id' => $arrDocument['id']
                            ]
                        ]
                    ]
                ]);
            }

            $this->getClient()->index($arrParams);

        } catch (\Exception $e) {

            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::ERROR, $e->getMessage(), ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_ERROR)]);

            return;
        }

        $objIndicesModel->last_indexed = time();
        $objIndicesModel->save();
    }

    /**
     * @param $strIndicesId
     * @return void
     * @throws \Elastic\Elasticsearch\Exception\ClientResponseException
     * @throws \Elastic\Elasticsearch\Exception\MissingParameterException
     * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
     */
    public function indexDocuments($strIndicesId)
    {

        if (!$strIndicesId) {
            return;
        }

        $this->connect();
        $arrDocuments = $this->getIndex($strIndicesId);

        if (!$this->getClient()) {
            return;
        }

        $this->createMapping($strIndicesId);

        foreach ($arrDocuments as $arrDocument) {

            $this->indexByDocument($arrDocument);
        }
    }

    public function createDocument($strIndicesId)
    {

        $objIndices = IndicesModel::findByPk($strIndicesId);

        if (!$objIndices) {
            return false;
        }

        $arrDomDocument = \StringUtil::deserialize($objIndices->document, true);

        $arrDocument = [
            'id' => $strIndicesId,
            'title' => $objIndices->title ?: '',
            'description' => $objIndices->description ?: '',
            'url' => $objIndices->url,
            'domain' => $objIndices->domain,
            'language' => $objIndices->language
        ];

        $arrTypes = \StringUtil::deserialize($objIndices->types, true);
        $objMicroData = MicrodataModel::findByPid($strIndicesId);

        if ($objMicroData) {
            while ($objMicroData->next()) {
                if ($objMicroData->type && !in_array($objMicroData->type, $arrTypes)) {
                    $arrTypes[] = $objMicroData->type;
                }
            }
        }

        $arrDocument['types'] = $arrTypes;
        foreach ($arrDomDocument as $strField => $varValues) {
            if (is_array($varValues)) {
                $varValues = implode(', ', $varValues);
            }
            $arrDocument[$strField] = $varValues;
        }

        return $arrDocument;
    }

    /**
     * @return string
     */
    protected function getQueryAnalyzer(): string
    {

        if ($this->objModule) {
            if ($this->objModule->psAnalyzer) {
                return $this->objModule->psAnalyzer;
            }
        }

        return 'contao';
    }

    /**
     * @param $arrKeywords
     * @return array|array[]
     * @throws \Elastic\Elasticsearch\Exception\ClientResponseException
     * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
     */
    public function autocompltion($arrKeywords): array
    {

        $arrResults = [
            'hits' => [],
            'didYouMean' => []
        ];

        $strRootPageId = $this->objRoot ? $this->objRoot->id : '';
        $strAnalyzer = $this->getQueryAnalyzer();

        $params = [
            "index" => $this->getIndexName($strRootPageId),
            "body" => [
                "size" => 0,
                "aggs" => [
                    "autocomplete" => [
                        "terms" => [
                            "field" => "autocomplete",
                            "order" => [
                                "_count" => "desc"
                            ],
                            "include" => $arrKeywords['query'] . ".*"
                        ]
                    ]
                ],
                "query" => [
                    "prefix" => [
                        "autocomplete" => [
                            "value" => $arrKeywords['query']
                        ]
                    ]
                ],
                "suggest" => [
                    "didYouMean" => [
                        "text" => $arrKeywords['query'],
                        "phrase" => [
                            "field" => "autocomplete",
                            "size" => 1,
                            "gram_size" => 3,
                            "analyzer" => $strAnalyzer,
                            "direct_generator" => [
                                [
                                    "field" => "autocomplete",
                                    "suggest_mode" => "always"
                                ]
                            ],
                            "highlight" => [
                                "pre_tag" => '<em>',
                                "post_tag" => '</em>'
                            ]
                        ]
                    ]
                ]
            ]
        ];

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
            if (isset($arrSuggest['options']) && is_array($arrSuggest['options'])) {
                foreach ($arrSuggest['options'] as $arrOption) {
                    $arrResults['didYouMean'][] = $arrOption['text'];
                }
            }
        }

        return $arrResults;
    }

    /**
     * @param $arrKeywords
     * @param array $arrOptions
     * @param bool $blnTryItAgain
     * @return array|array[]
     * @throws \Elastic\Elasticsearch\Exception\ClientResponseException
     * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
     */
    public function search($arrKeywords, array $arrOptions = [], bool $blnTryItAgain = true): array
    {

        $arrResults = [
            'hits' => [],
            'didYouMean' => []
        ];

        $strRootPageId = $this->objRoot ? $this->objRoot->id : '';
        $strAnalyzer = $arrOptions['analyzer'] ?? $this->getQueryAnalyzer();

        $params = [
            'index' => $this->getIndexName($strRootPageId),
            'body' => [
                "size" => $this->getSizeValue(),
                'query' => [
                    'bool' => []
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
                'suggest' => [
                    'didYouMean' => [
                        'text' => $arrKeywords['query'],
                        'phrase' => [
                            'field' => "autocomplete",
                            "size" => 1,
                            "gram_size" => 3,
                            "analyzer" => $strAnalyzer,
                            "direct_generator" => [
                                [
                                    "field" => "autocomplete",
                                    "suggest_mode" => "always"
                                ]
                            ],
                            "highlight" => [
                                "pre_tag" => '<em>',
                                "post_tag" => '</em>'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        if (isset($arrKeywords['query']) && $arrKeywords['query']) {

            $arrMustMatch = [
                'query' => $arrKeywords['query'],
                'analyzer' => $strAnalyzer,
                'type' => 'phrase_prefix',
                // 'boost' => 2,
                'fields' => ['title', 'description', 'text', 'document']
            ];

            $arrShouldMatch = [
                'query' => $arrKeywords['query'],
                'analyzer' => $strAnalyzer,
                'type' => 'phrase_prefix',
                'fields' => ['title^5', 'h1^10', 'strong', 'h2^2', 'h3', 'h4', 'h5', 'h6']
            ];

            if (isset($arrOptions['fuzziness'])) {

                $arrMustMatch['fuzziness'] = 'AUTO';
                $arrMustMatch['type'] = 'best_fields';

                $arrShouldMatch['fuzziness'] = 'AUTO';
                $arrShouldMatch['type'] = 'best_fields';
            }

            $params['body']['query']['bool'] = [
                'must' => [
                    [
                        'multi_match' => $arrMustMatch
                    ]
                ],
                'should' => [
                    [
                        'multi_match' => $arrShouldMatch
                    ]
                ]
            ];
        }

        $params['body']['query']['bool']['filter'][] = [
            'term' => [
                'language' => $this->objRoot ? $this->objRoot->language : $GLOBALS['TL_LANGUAGE'],
            ]
        ];

        $params['body']['query']['bool']['filter'][] = [
            'term' => [
                'domain' => \Environment::get('host')
            ]
        ];

        if (isset($arrKeywords['types']) && is_array($arrKeywords['types']) && !empty($arrKeywords['types'])) {
            foreach ($arrKeywords['types'] as $strType) {
                $params['body']['query']['bool']['filter'][] = [
                    'term' => [
                        'types' => $strType,
                        'case_insensitive' => false
                    ]
                ];
            }
        }

        if (empty($params['body']['query'])) {
            return $arrResults;
        }

        $response = $this->getClient()->search($params);

        $arrHits = $response['hits']['hits'] ?? [];
        $arrSuggests = $response['suggest']['didYouMean'] ?? [];

        foreach ($arrSuggests as $arrSuggest) {
            if (isset($arrSuggest['options']) && is_array($arrSuggest['options'])) {
                foreach ($arrSuggest['options'] as $arrOption) {
                    $arrResults['didYouMean'][] = $arrOption['text'];
                }
            }
        }

        foreach ($arrHits as $arrHit) {
            $objEntity = new Result();
            $objEntity->addHit($arrHit['_source']['id'], ($arrHit['highlight'] ?? []), [
                'types' => $arrHit['_source']['types'],
                'score' => $arrHit['_score'],
                'module' => $this->objModule
            ]);
            if ($arrResult = $objEntity->getResult()) {
                $arrResults['hits'][] = $arrResult;
            }
        }

        if (empty($arrResults['hits']) && $blnTryItAgain) {
            return $this->search($arrKeywords, [
                'fuzziness' => 'AUTO'
            ], false);
        }

        return $arrResults;
    }

    /**
     * @return array|array[]
     */
    public function getAnalyzer(): array
    {

        return $this->arrAnalyzer;
    }

    protected function getSizeValue()
    {

        if (!$this->objModule) {
            return 100;
        }

        return $this->objModule->perPage ?: 100;
    }
}