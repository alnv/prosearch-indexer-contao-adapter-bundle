<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;

class ElasticsearchAdapter {

    public function getIndex($intLimit=100) {

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

    protected function createDocument($strIndicesId) {

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