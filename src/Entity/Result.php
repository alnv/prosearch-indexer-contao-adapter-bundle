<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Entity;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Validator;

class Result
{
    protected array $arrHit;

    public function addHit(string $strId, array $arrHighlights, array $arrSource = []): void
    {

        $this->arrHit = [
            'id' => $strId,
            'highlights' => $arrHighlights,
            'source' => $arrSource
        ];
    }

    /**
     * @return array|void
     */
    public function getResult()
    {

        $arrImages = [];
        $objDocument = IndicesModel::findByPk($this->arrHit['id']);

        if (!$objDocument) {
            return;
        }

        if ($objDocument->state == States::DELETE) {
            return;
        }

        $arrSettings = StringUtil::deserialize($objDocument->settings, true);
        if (in_array('doNotShow', $arrSettings)) {
            return;
        }

        foreach (StringUtil::deserialize($objDocument->images, true) as $strFileId) {

            $blnPath = false;

            if (Validator::isBinaryUuid($strFileId) || Validator::isStringUuid($strFileId)) {
                $objFile = FilesModel::findByUuid($strFileId);
            } else {
                $objFile = FilesModel::findByPath($strFileId);
                $blnPath = true;
            }

            if ($objFile) {
                $arrImage = $objFile->row();
                $arrImage['icon'] = false;
                $arrImage['pid'] = $arrImage['pid'] ? StringUtil::binToUuid($arrImage['pid']) : '';
                $arrImage['uuid'] = StringUtil::binToUuid($arrImage['uuid']);
                $arrImage['meta'] = StringUtil::deserialize($arrImage['meta'], true);
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

        $arrHighlights = [];
        foreach ($this->arrHit['highlights'] as $arrFields) {
            foreach ($arrFields as $arrHighlight) {
                $arrHighlights[] = $arrHighlight;
            }
        }

        $strSummary = (!empty($arrHighlights) ? implode(' ', $arrHighlights) : $objDocument->description);
        $strSummary = StringUtil::substrHtml($strSummary, 250, '…');

        $blnOpenDocumentsInBrowser = $this->arrHit['source']['elasticOptions']['openDocumentsInBrowser'] ?? false;
        $blnUseUseRichSnippets = $this->arrHit['source']['elasticOptions']['useUseRichSnippets'] ?? false;

        $arrReturn = [
            'images' => $arrImages,
            'url' => $objDocument->url,
            'origin_url' => $objDocument->origin_url,
            'usedUrl' => !$blnOpenDocumentsInBrowser ? ($objDocument->origin_url?$objDocument->origin_url:$objDocument->url) : $objDocument->url,
            'id' => $this->arrHit['id'],
            'target' => $objDocument->origin_url && $blnOpenDocumentsInBrowser ? '_target' : '_self',
            'highlights' => $arrHighlights,
            'title' => $objDocument->title,
            'doc_type' => $objDocument->doc_type ?: '',
            'description' => $objDocument->description,
            'summary' => $strSummary,
            'mainImage' => $arrImages[0] ?? [],
            'types' => $this->arrHit['source']['types'] ?? [],
            'score' => $this->arrHit['source']['score'] ?? 0,
            'microdata' => [],
            'rich_snippet' => ''
        ];

        if (($objMicroData = MicrodataModel::findAll(['column' => ['pid=?'], 'value' => [$this->arrHit['id']]])) && $blnUseUseRichSnippets) {

            $arrMicrodata = [];

            while ($objMicroData->next()) {

                if (!$objMicroData->type || !is_array($GLOBALS['PS_MICRODATA_CLASSES']) || !isset($GLOBALS['PS_MICRODATA_CLASSES'][$objMicroData->type])) {
                    continue;
                }

                $strClass = $GLOBALS['PS_MICRODATA_CLASSES'][$objMicroData->type];

                if (!isset($arrMicrodata[$objMicroData->type])) {
                    $arrMicrodata[$objMicroData->type] = [];
                }

                $objMicroDataClass = new $strClass(StringUtil::deserialize($objMicroData->data, true));
                $objMicroDataClass->match($this->arrHit['source']['keywords']);
                $arrMicrodata[$objMicroData->type][] = $objMicroDataClass;
            }

            $arrReturn['microdata'] = $arrMicrodata;
            $arrReturn['rich_snippet'] = $this->getRichSnippets($arrMicrodata, $arrReturn);
        }

        return $arrReturn;
    }

    protected function getRichSnippets($arrMicrodata, $arrData = []): string
    {

        $arrRichSnippets = [];

        foreach ($arrMicrodata as $strType => $arrEntities) {

            $intCount = count($arrEntities);

            foreach ($arrEntities as $objMicroData) {

                if (!$objMicroData->richSnippet) {
                    continue;
                }

                $arrJsonLdScriptsData = $objMicroData->getJsonLdScriptsData();
                $strId = md5(serialize($arrJsonLdScriptsData));

                if ($this->arrHit['source']['elasticOptions']['usedKeyWord'] && $strType !== $this->arrHit['source']['elasticOptions']['usedKeyWord']) {
                    continue;
                }

                if ($intCount > 1 && !$arrJsonLdScriptsData['_matched']) {
                    continue;
                }

                $arrRichSnippets[$strId] = $objMicroData->generate($arrData);
            }
        }

        return implode('', $arrRichSnippets);
    }
}