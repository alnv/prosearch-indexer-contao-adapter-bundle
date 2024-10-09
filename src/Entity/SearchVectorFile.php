<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Entity;

use Alnv\ContaoOpenAiAssistantBundle\Library\Automator;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Contao\Database;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;

class SearchVectorFile
{

    protected string $strSearchVectorFileId;

    protected array $arrSearchVectorFile = [];

    public function __construct($strSearchVectorFileId)
    {

        $this->strSearchVectorFileId = $strSearchVectorFileId;

        $this->setVectorFile();
    }

    protected function setVectorFile(): array
    {

        $arrEntity = Database::getInstance()->prepare('SELECT * FROM tl_search_vector_files WHERE id=?')->limit(1)->execute($this->strSearchVectorFileId)->row();

        foreach ($arrEntity as $strField => $strValue) {

            switch ($strField) {
                case 'name':
                    $this->arrSearchVectorFile[$strField] = $strValue;
                    break;
                case 'fields':
                case 'types':
                    $this->arrSearchVectorFile[$strField] = StringUtil::deserialize($strValue, true);
                    break;
                case 'file':
                    $objFile = FilesModel::findByUuid($strValue);
                    $this->arrSearchVectorFile[$strField] = $objFile ? $objFile->path : '';
                    break;
            }
        }

        return $this->arrSearchVectorFile;
    }

    public function update(): void
    {
        Automator::updateVectorStoresByFilePath($this->arrSearchVectorFile['file'], ($this->arrSearchVectorFile['name'] ?? ''));
    }

    public function save($strFolder = ''): string
    {

        $objCategories = new Categories();
        $arrCategories = $objCategories->getTranslatedCategories();

        $strText = "";
        $strRootDir = System::getContainer()->getParameter('kernel.project_dir');
        $objIndices = Database::getInstance()->prepare('SELECT * FROM tl_indices WHERE state=? ORDER BY id')->execute(States::ACTIVE);

        if ($strFolder && !\file_exists($strRootDir . '/' . $strFolder)) {

            \mkdir($strRootDir . '/' . $strFolder);

            Dbafs::addResource($strFolder);
        }

        while ($objIndices->next()) {

            $arrTypes = StringUtil::deserialize($objIndices->types, true);
            $arrMatched = array_intersect($arrTypes, $this->arrSearchVectorFile['types']);

            if (empty($arrMatched)) {
                continue;
            }

            $arrLabeledTypes = [];
            foreach ($arrTypes as $strType) {
                $arrLabeledTypes[] = $arrCategories[$strType]['label'] ?? $strType;
            }

            $strText .= 'INDEX-ID: ' . $objIndices->id . PHP_EOL;
            $strText .= 'PAGE-ID: ' . $objIndices->pageId . PHP_EOL;
            $strText .= 'URL: ' . $objIndices->url . PHP_EOL;
            $strText .= 'Seiten-Typ: ' . implode(', ', $arrLabeledTypes) . PHP_EOL;
            $strText .= 'Sprache: ' . $objIndices->language . PHP_EOL;
            $strText .= 'Titel: ' . $this->parseString($objIndices->title) . PHP_EOL;
            if (in_array('description', $this->arrSearchVectorFile['fields'])) {
                $strText .= 'Beschreibung: ' . $this->parseString(($objIndices->description ?: '-')) . PHP_EOL;
            }
            $strText .= 'Seiteninhalt: ' . PHP_EOL;

            foreach (StringUtil::deserialize($objIndices->document, true) as $strDocumentType => $varValues) {
                if (!$varValues) {
                    continue;
                }
                if (!in_array($strDocumentType, $this->arrSearchVectorFile['fields'])) {
                    continue;
                }
                $strText .= $this->parseString(implode('. ', $varValues)) . PHP_EOL;
            }

            if (in_array('microdata', $this->arrSearchVectorFile['fields'])) {

                $strText .= 'Schema.org: ' . PHP_EOL;
                $objMicrodata = Database::getInstance()->prepare('SELECT * FROM tl_microdata WHERE pid=? ORDER BY `type`')->execute($objIndices->id);
                while ($objMicrodata->next()) {
                    $arrMicrodata = StringUtil::deserialize($objMicrodata->data, true);
                    $strText .= \json_encode($arrMicrodata, 0, 512) . PHP_EOL;
                }
            }

            $strText .= PHP_EOL . PHP_EOL . PHP_EOL;
        }

        $strFileName = StringUtil::generateAlias($this->arrSearchVectorFile['name']);

        file_put_contents($strRootDir . '/' . $strFolder . '/' . $strFileName . '.txt', $strText);

        $objFile = Dbafs::addResource($strFolder . '/' . $strFileName . '.txt');

        return $objFile ? StringUtil::binToUuid($objFile->uuid) : '';
    }

    protected function parseString($varValue): string
    {
        $varValue = strip_tags($varValue);
        $varValue = str_replace('"', '', $varValue);
        $varValue = str_replace(',', ' ', $varValue);
        $varValue = str_replace('\'', '', $varValue);
        $varValue = str_replace(["\r", "\n"], ' ', $varValue);
        $varValue = str_replace("&nbsp;", '', $varValue);
        $varValue = StringUtil::decodeEntities($varValue);
        $varValue = mb_convert_encoding($varValue, 'UTF-8');

        return trim($varValue);
    }
}