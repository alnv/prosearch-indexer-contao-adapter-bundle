<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\System;
use Elastic\Elasticsearch\ClientBuilder;
use Psr\Log\LogLevel;

// https://github.com/elastic/elasticsearch-php
class ElasticsearchAdapter
{

    public function connect()
    {

        $arrCredentials = (new Credentials())->getCredentials();

        if ($arrCredentials === false) {
            return;
        }

        switch ($arrCredentials['type']) {
            case 'elasticsearch':
                try {
                    $objClient = ClientBuilder::create()
                        ->setHosts([$arrCredentials['host'] . ($arrCredentials['port'] ? ':' . $arrCredentials['port'] : '')])
                        ->setBasicAuthentication($arrCredentials['host'], $arrCredentials['password'])
                        ->setCABundle($arrCredentials['cert'])
                        ->build();

                    // todo

                } catch (\Exception $objError) {
                    System::getContainer()
                        ->get('monolog.logger.contao')
                        ->log(LogLevel::ERROR, $objError->getMessage(), ['contao' => new ContaoContext(__CLASS__.'::'.__FUNCTION__, TL_ERROR)]);
                }
                break;
            case 'elasticsearch_cloud':
                try {
                    $objClient = ClientBuilder::create()
                        ->setElasticCloudId($arrCredentials['cloudid'])
                        ->setApiKey($arrCredentials['key'])
                        ->build();

                    // todo

                } catch (\Exception $objError) {
                    System::getContainer()
                        ->get('monolog.logger.contao')
                        ->log(LogLevel::ERROR, $objError->getMessage(), ['contao' => new ContaoContext(__CLASS__.'::'.__FUNCTION__, TL_ERROR)]);
                    exit;
                }

                break;
            case 'licence':

                // todo

                break;
        }
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
            return;
        }

        $arrDocuments = [];

        while ($objIndices->next()) {
            $arrDocuments[$objIndices->url] = $this->createDocument($objIndices->id);
        }

        //
    }

    protected function createDocument($strIndicesId)
    {

        $objIndices = IndicesModel::findByPk($strIndicesId);

        if (!$objIndices) {
            return false;
        }

        $arrDomDocument = \StringUtil::deserialize($objIndices->document, true);

        $arrDocument = [
            'origin_id' => $strIndicesId,
            'title' => $objIndices->title ?: '',
            'description' => $objIndices->description ?: '',
            'url' => $objIndices->url,
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

        if (!empty($arrDocument['types'])) {
            // dd($arrDocument);
        }

        return $arrDocument;
    }
}