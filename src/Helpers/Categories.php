<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\System;
use Contao\Database;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;

class Categories
{

    public function getCategories(): array
    {

        $this->setCategories();

        $arrCategories = [];
        $objCategories = Database::getInstance()->prepare('SELECT * FROM tl_ps_categories ORDER BY tstamp')->execute();

        while ($objCategories->next()) {
            $arrCategories[] = $objCategories->category;
        }

        return $arrCategories;
    }

    public function getTranslatedCategories(): array
    {

        $this->setCategories();

        $arrCategories = [];
        $strCurrentLanguage = $GLOBALS['TL_LANGUAGE'] ?? '';

        if (!$strCurrentLanguage) {
            $objRootPage = Database::getInstance()->prepare('SELECT * FROM tl_page WHERE (type=? OR type=?) AND `language`!=?')->limit(1)->execute('rootfallback', 'root', '');
            if ($objRootPage->numRows) {
                $strCurrentLanguage = $objRootPage->language;
            }
        }

        $objCategories = Database::getInstance()->prepare('SELECT * FROM tl_ps_categories WHERE exist=? ORDER BY category')->execute('1');

        while ($objCategories->next()) {

            if (!$objCategories->category) {
                continue;
            }

            $strLabel = $objCategories->category;
            $arrTranslations = StringUtil::deserialize($objCategories->translating, true);

            foreach ($arrTranslations as $arrTranslation) {
                if ($arrTranslation['language'] == $strCurrentLanguage && $arrTranslation['label']) {
                    $strLabel = $arrTranslation['label'];
                }
            }

            $arrSet = [
                'id' => $objCategories->id,
                'key' => $objCategories->category,
                'label' => StringUtil::decodeEntities($strLabel)
            ];

            $arrCategories[$objCategories->category] = $arrSet;
        }

        return $arrCategories;
    }

    public function setCategories(): void
    {

        $arrCategories = [];

        $objIndices = Database::getInstance()->prepare('SELECT DISTINCT (types) FROM tl_indices WHERE state=?')->execute(States::ACTIVE);
        while ($objIndices->next()) {
            foreach (StringUtil::deserialize($objIndices->types, true) as $strType) {

                if (!$strType) {
                    continue;
                }

                $strType = trim($strType);

                if (!in_array($strType, $arrCategories)) {
                    $arrCategories[] = $strType;
                }
            }
        }

        $objMicrodata = Database::getInstance()->prepare('SELECT DISTINCT (type) FROM tl_microdata')->execute();
        while ($objMicrodata->next()) {

            if (!$objMicrodata->type) {
                continue;
            }

            $objMicrodata->type = trim($objMicrodata->type);

            if (!in_array($objMicrodata->type, $arrCategories)) {
                $arrCategories[] = $objMicrodata->type;
            }
        }

        if (System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest(System::getContainer()->get('request_stack')->getCurrentRequest() ?? Request::create(''))) {
            Database::getInstance()->prepare('UPDATE tl_ps_categories %s')->set(['tstamp' => time(), 'exist' => ''])->execute();
        }

        foreach ($arrCategories as $strCategory) {

            $arrSet = [
                'exist' => '1',
                'tstamp' => time(),
                'category' => $strCategory
            ];

            $objCategory = Database::getInstance()->prepare('SELECT * FROM tl_ps_categories WHERE category=?')->limit(1)->execute($strCategory);

            if ($objCategory->numRows) {
                Database::getInstance()->prepare('UPDATE tl_ps_categories %s WHERE id=?')->set($arrSet)->execute($objCategory->id);
            } else {
                Database::getInstance()->prepare('INSERT INTO tl_ps_categories %s')->set($arrSet)->execute();
            }
        }
    }
}