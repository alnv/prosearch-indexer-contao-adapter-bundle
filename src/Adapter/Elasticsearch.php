<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Authorization;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Credentials;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\Environment;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Elastic\Elasticsearch\ClientBuilder;
use Psr\Log\LogLevel;

// https://github.com/elastic/elasticsearch-php
class Elasticsearch extends Adapter
{

    public const INDEX = 'contao_search';

    private string $strSignature = "";

    private array $arrCredentials = [];

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
                    System::getContainer()
                        ->get('monolog.logger.contao')
                        ->log(LogLevel::ERROR, $objError->getMessage(), ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);
                }
                break;
            case 'elasticsearch_cloud':
                try {
                    $this->objClient = ClientBuilder::create()
                        ->setElasticCloudId($this->arrCredentials['cloudid'])
                        ->setApiKey($this->arrCredentials['key'])
                        ->build();
                } catch (\Exception $objError) {
                    System::getContainer()
                        ->get('monolog.logger.contao')
                        ->log(LogLevel::ERROR, $objError->getMessage(), ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);
                    exit;
                }
                break;
            case 'licence':
                $objAuthorization = new Authorization;
                $strDomain = Environment::get('httpHost');
                $arrLicenseKeys = StringUtil::deserialize($this->arrCredentials['keys'], true);
                if (empty($arrLicenseKeys)) {
                    $strLicense = $this->arrCredentials['key'] ?? '';
                } else {
                    $strLicense = $objAuthorization->pluckKeyFromKeysGlobalByDomain(StringUtil::deserialize($this->arrCredentials['keys'], true), $strDomain);
                }
                $this->strLicense = $objAuthorization->encodeLicense($strLicense, $strDomain, ($this->arrCredentials['authToken']??''));
                return;
        }

        if (!$this->strSignature) {
            $this->objClient = null;
        }

        if (!$this->objClient) {
            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::ERROR, 'No connection to the server could be established', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);
        }
    }

    protected function getRootIdFromIndicesId($strIndicesId): string
    {

        if ($objIndicesModel = IndicesModel::findByPk($strIndicesId)) {
            $objPage = PageModel::findByPk($objIndicesModel->pageId);

            if ($objPage) {
                $objPage->loadDetails();
                return $objPage->rootId;
            }
        }

        return '';
    }

    public function getIndexName($strRootId): string
    {

        return Elasticsearch::INDEX . '_' . $this->strSignature . ($strRootId ? '_' . $strRootId : '');
    }

    public function getClient()
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

                System::getContainer()
                    ->get('monolog.logger.contao')
                    ->log(LogLevel::ERROR, $objError->getMessage(), ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);
            }
        }

        Database::getInstance()->prepare('DELETE FROM tl_indices')->execute();
        Database::getInstance()->prepare('DELETE FROM tl_microdata')->execute();
    }

    public function deleteDatabase($strIndex): void
    {

        $objCurl = curl_init();

        curl_setopt($objCurl, CURLOPT_URL, "http://" . ($this->arrCredentials['host'] ?? '') . ":" . ($this->arrCredentials['port'] ?? '') . "/" . $strIndex);
        curl_setopt($objCurl, CURLOPT_CUSTOMREQUEST, 'DELETE');

        if ($this->arrCredentials['username'] && $this->arrCredentials['password']) {
            curl_setopt($objCurl, CURLOPT_HTTPHEADER, array('Authorization:Basic ' . base64_encode($this->arrCredentials['username'] . ':' . $this->arrCredentials['password'])));
        }

        curl_exec($objCurl);
        curl_close($objCurl);
    }

    public function deleteIndex($strIndicesId): void
    {
        $this->connect();

        $strIndex = $this->getIndexName($this->getRootIdFromIndicesId($strIndicesId));
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

    protected function createMapping(): void
    {

        $this->connect();

        $strAnalyzer = $this->arrOptions['analyzer'];
        $strIndex = $this->getIndexName($this->arrOptions['rootPageId']);
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

        $blnExists = $this->getClient()->indices()->exists([
            "index" => $arrParams['index']
        ])->asBool();

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

        $strIndex = $this->getIndexName($this->getRootIdFromIndicesId($arrDocument['id']));

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

                System::getContainer()
                    ->get('monolog.logger.contao')
                    ->log(LogLevel::ERROR, $e->getMessage(), ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);

                return;
            }
        }

        $objIndicesModel->last_indexed = time();
        $objIndicesModel->save();
    }

    public function clientIndex($arrParams)
    {

        if (!$this->getClient()) {
            return;
        }

        try {

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

        } catch (\Exception $objError) {

            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::DEBUG, $objError->getMessage(), ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);

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

    public function autocompltion($arrKeywords, string $strIndexName = ''): array
    {

        $arrResults = [
            'hits' => [],
            'didYouMean' => []
        ];

        $strRootPageId = $this->arrOptions['rootPageId'];
        $strAnalyzer = $this->arrOptions['analyzer'];

        if (!$strIndexName) {
            $strIndexName = $this->getIndexName($strRootPageId);
        }

        $params = [
            "index" => $strIndexName,
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

    public function search($arrKeywords, string $strIndexName = '', int $intTryCounts = 0): array
    {

        $arrResults = [
            'hits' => [],
            'didYouMean' => []
        ];

        $strRootPageId = $this->arrOptions['rootPageId'];
        $strAnalyzer = $this->arrOptions['analyzer'];

        if (!$strIndexName) {
            $strIndexName = $this->getIndexName($strRootPageId);
        }

        $params = [
            'index' => $strIndexName,
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

            switch ($intTryCounts) {
                case 0:
                    $params['body']['query']['bool'] = [
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => $arrKeywords['query'],
                                    'analyzer' => $strAnalyzer,
                                    'type' => 'phrase_prefix',
                                    'fields' => ['description', 'text', 'document']
                                ]
                            ]
                        ],
                        'should' => [
                            [
                                'multi_match' => [
                                    'query' => $arrKeywords['query'],
                                    'analyzer' => $strAnalyzer,
                                    'type' => 'phrase_prefix',
                                    'fields' => ['title^3', 'h1^5', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong']
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
                                    'query' => '*' . $arrKeywords['query'] . '*',
                                    'fields' => ['title', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'description', 'text', 'document']
                                ]
                            ]
                        ],
                        'should' => [
                            [
                                'multi_match' => [
                                    'query' => $arrKeywords['query'],
                                    'analyzer' => $strAnalyzer,
                                    'type' => 'best_fields',
                                    'fields' => ['title', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong']
                                ]
                            ]
                        ]
                    ];
                    break;
                case 2:
                    $params['body']['query']['bool'] = [
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => $arrKeywords['query'],
                                    'analyzer' => $strAnalyzer,
                                    'type' => 'best_fields',
                                    'fuzziness' => 'AUTO',
                                    'fields' => ['description', 'text', 'document']
                                ]
                            ]
                        ],
                        'should' => [
                            [
                                'multi_match' => [
                                    'query' => $arrKeywords['query'],
                                    'analyzer' => $strAnalyzer,
                                    'type' => 'best_fields',
                                    'fuzziness' => 'AUTO',
                                    'fields' => ['title', 'h1', 'strong', 'h2^2', 'h3', 'h4', 'h5', 'h6']
                                ]
                            ]
                        ]
                    ];
                    break;
            }
        }

        $params['body']['query']['bool']['filter'][] = [
            'term' => [
                'language' => $this->arrOptions['language'],
            ]
        ];

        $params['body']['query']['bool']['filter'][] = [
            'term' => [
                'domain' => $this->arrOptions['domain']
            ]
        ];

        if (isset($arrKeywords['types']) && is_array($arrKeywords['types']) && !empty($arrKeywords['types'])) {
            $arrLowerCaseTypes = array_map(function ($strType) {
                return strtolower($strType);
            }, $arrKeywords['types']);
            $params['body']['query']['bool']['filter'][] = [
                'terms' => [
                    'types' => $arrLowerCaseTypes,
                ]
            ];
        }

        if (empty($params['body']['query'])) {
            return $arrResults;
        }

        $response = $this->getClient()->search($params);

        $arrResults['hits'] = $response['hits']['hits'] ?? [];

        foreach (($response['suggest']['didYouMean'] ?? []) as $arrSuggest) {
            if (isset($arrSuggest['options']) && is_array($arrSuggest['options'])) {
                foreach ($arrSuggest['options'] as $arrOption) {
                    $arrResults['didYouMean'][] = $arrOption['text'];
                }
            }
        }

        $intMaxTryCounts = 1;
        if (isset($this->arrOptions['fuzzy']) && $this->arrOptions['fuzzy'] === true) {
            $intMaxTryCounts = 2;
        }

        if (empty($arrResults['hits']) && $intMaxTryCounts > $intTryCounts) {
            $intNextTryCount = $intTryCounts + 1;
            return $this->search($arrKeywords, $strIndexName, $intNextTryCount);
        }

        return $arrResults;
    }

    public function getAnalyzer(): array
    {
        return $this->arrAnalyzer;
    }

    protected function getSizeValue()
    {
        return $this->arrOptions['perPage'] ?: 100;
    }

    public function getLicense(): string
    {
        return $this->strLicense;
    }
}