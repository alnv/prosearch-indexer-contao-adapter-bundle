<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Entity;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;

/**
 *
 */
class Result
{
    /**
     * @var array
     */
    protected array $arrHit;

    /**
     * @param string $strId
     * @param array $arrHighlights
     * @param array $arrSource
     * @return void
     */
    public function addHit(string $strId, array $arrHighlights, array $arrSource=[]) {

        $this->arrHit = [
            'id' => $strId,
            'highlights' => $arrHighlights,
            'source' => $arrSource
        ];
    }

    /**
     * @return array|void
     */
    public function getResult() {

        $arrImages = [];
        $objDocument = IndicesModel::findByPk($this->arrHit['id']);

        if (!$objDocument) {
            return;
        }

        if ($objDocument->state == States::DELETE) {
            return;
        }

        foreach (\StringUtil::deserialize($objDocument->images, true) as $strFileId) {

            $blnPath = false;

            if (\Validator::isBinaryUuid($strFileId) || \Validator::isStringUuid($strFileId)) {
                $objFile = \FilesModel::findByUuid($strFileId);
            } else {
                $objFile = \FilesModel::findByPath($strFileId);
                $blnPath = true;
            }

            if ($objFile) {
                $arrImage = $objFile->row();
                $arrImage['icon'] = false;
                $arrImage['pid'] = \StringUtil::binToUuid($arrImage['pid']);
                $arrImage['uuid'] = \StringUtil::binToUuid($arrImage['uuid']);
                $arrImage['meta'] = \StringUtil::deserialize($arrImage['meta'], true);
                $arrImages[] = $arrImage;
            }

            if ($blnPath && !$objFile) {
                $arrImages[] = [
                    'meta' => [],
                    'icon' => true,
                    'path' => $strFileId
                ];
            }
        }

        $arrReturn = [
            'id' => $this->arrHit['id'],
            'highlights' => $this->arrHit['highlights'],
            'title' => $objDocument->title,
            'description' => $objDocument->description,
            'url' => $objDocument->url,
            'images' => $arrImages,
            'types' => $this->arrHit['source']['types'] ?? [],
            'score' => $this->arrHit['source']['score'] ?? 0,
            'microdata' => []
        ];

        $objMicroData = MicrodataModel::findAll([
            'column' => ['pid=?'],
            'value' => [$this->arrHit['id']]
        ]);

        if ($objMicroData) {
            while ($objMicroData->next()) {
                $arrReturn['microdata'][] = [
                    'type' => $objMicroData->type,
                    'data' => \StringUtil::deserialize($objMicroData->data, true)
                ];
            }
        }

        return $arrReturn;
    }

}