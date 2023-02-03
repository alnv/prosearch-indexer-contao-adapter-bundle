<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\System;
use Elastic\Elasticsearch\ClientBuilder;
use Psr\Log\LogLevel;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;

// https://github.com/elastic/elasticsearch-php
class ElasticsearchAdapter
{

    private $strType;
    private $objClient;

    public function connect()
    {

        $arrCredentials = (new Credentials())->getCredentials();

        if ($arrCredentials === false) {

            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::ERROR, 'No credentials for elasticsearch found', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_ERROR)]);

            return;
        }

        $this->strType = $arrCredentials['type'];

        switch ($arrCredentials['type']) {
            case 'elasticsearch':
                try {
                    $this->objClient = ClientBuilder::create()
                        ->setHosts([$arrCredentials['host'] . ($arrCredentials['port'] ? ':' . $arrCredentials['port'] : '')])
                        ->setBasicAuthentication($arrCredentials['host'], $arrCredentials['password'])
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
                ->log(LogLevel::ERROR, 'No connection to elasticsearch found', ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__, TL_ERROR)]);
        }
    }

    public function getClient()
    {

        return $this->objClient;
    }

    public function getIndex($intLimit = 100)
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

    public function indexDocuments() {

        $this->connect();
        $arrDocuments = $this->getIndex();

        if (!$this->objClient) {
            return;
        }

        foreach ($arrDocuments as $arrDocument) {

            $objIndicesModel = IndicesModel::findByPk($arrDocument['id']);

            if (!$objIndicesModel) {
                continue;
            }

            $arrParams = [
                'index' => 'contao_search',
                'body'  => $arrDocument
            ];

            try {
                $this->objClient->index($arrParams);
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
            'microdata' => []
        ];

        $arrTypes = \StringUtil::deserialize($objIndices->types, true);
        $objMicroData = MicrodataModel::findByPid($strIndicesId);

        if ($objMicroData) {
            while ($objMicroData->next()) {
                if ($objMicroData->type && !in_array($objMicroData->type, $arrTypes)) {
                    $arrTypes[] = $objMicroData->type;
                    $arrData = \StringUtil::deserialize($objMicroData->data, true);
                    switch ($objMicroData->type) {
                        // todo get data from micordata and add it to document
                    }
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
}