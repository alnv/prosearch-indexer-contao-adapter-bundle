<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Entity;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;
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

        if ($objDocument->protected) {

            $arrGroups = StringUtil::deserialize($objDocument->groups, true);
            if (empty($arrGroups)) {
                return;
            }

            if (!System::getContainer()->get('security.helper')->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, $arrGroups)) {
                return;
            }
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

        $arrReturn = [
            'images' => $arrImages,
            'url' => $objDocument->url,
            'origin_url' => $objDocument->origin_url,
            'usedUrl' => !$blnOpenDocumentsInBrowser ? ($objDocument->origin_url ? $objDocument->origin_url : $objDocument->url) : $objDocument->url,
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
            'sort' => $this->arrHit['source']['sort'] ?? 0
        ];

        if (isset($GLOBALS['TL_HOOKS']['psParseSearchHit']) && is_array($GLOBALS['TL_HOOKS']['psParseSearchHit'])) {
            foreach ($GLOBALS['TL_HOOKS']['psParseSearchHit'] as $arrCallback) {
                $arrReturn = System::importStatic($arrCallback[0])->{$arrCallback[1]}($arrReturn, $this->arrHit, $this);
            }
        }

        return $arrReturn;
    }
}