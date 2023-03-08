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

    protected array $arrAnalyzer = [
        "german" => [
            "type" => "custom",
            "tokenizer" => "standard",
            "filter" => ["lowercase", "german_stopwords", "german_stemmer"]
        ],
        "english" => [
            "type" => "custom",
            "tokenizer" => "standard",
            "filter" => ["lowercase", "english_stopwords", "english_stemmer"]
        ]
    ];

    protected array $AnalyzerLanguageMap = [
        'en' => 'english',
        'en-US' => 'english',
        'de' => 'german',
        'de-DE' => 'german',
        'de-CH' => 'german',
        'de-AT' => 'german'
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

        if (!$this->objClient) {
            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::ERROR, 'No connection to the server could be established', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_ERROR)]);
        }
    }

    public function getClient()
    {

        return $this->objClient;
    }

    public function deleteIndex()
    {

        $this->connect();

        if (!$this->getClient()) {
            return;
        }

        // todo "curl -X DELETE http://localhost:9200/contao_search"
    }

    public function getIndex($intLimit = 50)
    {

        $objIndices = IndicesModel::findAll([
            'column' => ['state=?'],
            'value' => [States::ACTIVE],
            'order' => 'last_indexed ASC',
            'limit' => $intLimit
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

    protected function createMapping()
    {

        $this->connect();

        if (!$this->getClient()) {
            return;
        }

        $arrParams = [
            "index" => Elasticsearch::INDEX,
            "body" => [
                "settings" => [
                    "analysis" => [
                        "analyzer" => $this->arrAnalyzer,
                        "filter" => [
                            // english filters
                            "english_stemmer" => [
                                "type" => "stemmer",
                                "language" => "english"
                            ],
                            "english_stopwords" => [
                                "type" => "stop",
                                "stopwords" => ["_english_"]
                            ],
                            // german filters
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
                ]
            ]
        ];

        $blnExists = $this->getClient()->indices()->exists([
            "index" => Elasticsearch::INDEX
        ])->asBool();

        if (!$blnExists) {
            $this->getClient()->indices()->create($arrParams);
        }
    }

    public function indexDocuments()
    {

        $this->connect();
        $arrDocuments = $this->getIndex();

        if (!$this->getClient()) {
            return;
        }

        $this->createMapping();

        foreach ($arrDocuments as $arrDocument) {

            $objIndicesModel = IndicesModel::findByPk($arrDocument['id']);

            if (!$objIndicesModel) {
                continue;
            }

            $arrParams = [
                'index' => Elasticsearch::INDEX,
                'id' => $arrDocument['id'],
                'body' => $arrDocument
            ];

            try {

                if ($this->getClient()->exists(['index' => Elasticsearch::INDEX, 'id' => $arrDocument['id']])->asBool()) {
                    $this->getClient()->deleteByQuery([
                        'index' => Elasticsearch::INDEX,
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

                continue;
            }

            $objIndicesModel->last_indexed = time();
            $objIndicesModel->save();
        }
    }

    protected function createDocument($strIndicesId)
    {

        $objIndices = IndicesModel::findByPk($strIndicesId);

        if (!$objIndices) {
            return false;
        }

        $arrDomDocument = \StringUtil::deserialize($objIndices->document, true);
        $arrUrlFragments = parse_url($objIndices->url);

        $arrDocument = [
            'id' => $strIndicesId,
            'title' => $objIndices->title ?: '',
            'description' => $objIndices->description ?: '',
            'url' => $objIndices->url,
            'domain' => $arrUrlFragments['host'] ?? '',
            'language' => $objIndices->language
            // 'microdata' => []
        ];

        $arrTypes = \StringUtil::deserialize($objIndices->types, true);
        $objMicroData = MicrodataModel::findByPid($strIndicesId);

        if ($objMicroData) {
            while ($objMicroData->next()) {
                if ($objMicroData->type && !in_array($objMicroData->type, $arrTypes)) {
                    $arrTypes[] = $objMicroData->type;
                    /*
                    $arrData = \StringUtil::deserialize($objMicroData->data, true);
                    switch ($objMicroData->type) {
                        // todo get data from micordata and add it to document
                    }
                    */
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
     * @param string $strLanguage
     * @return string
     */
    protected function getAnalyzerByLanguage(string $strLanguage = "") : string
    {

        if (!$strLanguage) {
            $strLanguage = $GLOBALS['TL_LANGUAGE'] ?: '';
        }

        if (!$strLanguage) {
            return 'standard';
        }

        return $this->getAnalyzerByLanguage[$strLanguage] ?? 'standard';
    }

    /**
     * @param $arrKeywords
     * @param $arrOptions
     * @return array
     * @throws \Elastic\Elasticsearch\Exception\ClientResponseException
     * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
     */
    public function search($arrKeywords, $arrOptions = []): array
    {

        $arrResults = [
            'hits' => [],
            'didYouMean' => []
        ];

        $strAnalyzer = $this->getAnalyzerByLanguage();

        $params = [
            'index' => Elasticsearch::INDEX,
            'body' => [
                'query' => [],
                'highlight' => [
                    'pre_tags' => '<strong>',
                    'post_tags' => '</strong>',
                    'fields' => [
                        'text' => new \stdClass()
                    ],
                    'require_field_match' => true,
                    'type' => 'plain',
                    'fragment_size' => 120,
                    'number_of_fragments' => 120,
                    'fragmenter' => 'span'
                ],
                'suggest' => [
                    'didYouMean' => [
                        'text' => $arrKeywords['query'],
                        'phrase' => [
                            'field' => "text",
                            "size" => 5,
                            "confidence" => 1,
                            "max_errors" => 2,
                            // "gram_size" => 3,
                            // "max_errors" => 2,
                            'analyzer' => $strAnalyzer,
                            'direct_generator' => [
                                [
                                    'field' => 'text',
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

        if (isset($arrKeywords['query']) && $arrKeywords['query']) {
            $params['body']['query']['bool'] = [
                'must' => [
                    [
                        'multi_match' => [
                            'query' => $arrKeywords['query'],
                            'fuzziness' => 'AUTO',
                            'analyzer' => $strAnalyzer,
                            'fields' => ['title', 'description', 'text', 'span']
                        ]
                    ]
                ],
                'should' => [
                    [
                        'multi_match' => [
                            'query' => $arrKeywords['query'],
                            'analyzer' => $strAnalyzer,
                            'fields' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong']
                        ]
                    ]
                ]
            ];
        }

        if (isset($arrKeywords['types']) && is_array($arrKeywords['types']) && !empty($arrKeywords['types'])) {
            $params['body']['query']['bool']['filter']['terms']['types'] = $arrKeywords['types'];
        }

        $params['body']['query']['bool']['filter']['terms']['language'] = [$GLOBALS['TL_LANGUAGE']];

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
            $objEntity->addHit($arrHit['_source']['id'], ($arrHit['highlight']['text'] ?? []), [
                'types' => $arrHit['_source']['types'],
                'score' => $arrHit['_score']
            ]);
            if ($arrResult = $objEntity->getResult()) {
                $arrResults['hits'][] = $arrResult;
            }
        }

        return $arrResults;
    }
}