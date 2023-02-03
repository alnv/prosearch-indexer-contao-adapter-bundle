<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

class Categories
{

    public function getCategories(): array
    {

        $this->setCategories();

        $arrCategories = [];
        $objCategories = \Database::getInstance()->prepare('SELECT * FROM tl_ps_categories ORDER BY tstamp')->execute();

        while ($objCategories->next()) {
            $arrCategories[] = $objCategories->category;
        }

        return $arrCategories;
    }

    public function setCategories()
    {

        $arrCategories = [];

        $objIndices = \Database::getInstance()->prepare('SELECT DISTINCT (types) FROM tl_indices WHERE state=?')->execute(States::ACTIVE);
        while ($objIndices->next()) {
            foreach (\StringUtil::deserialize($objIndices->types, true) as $strType) {
                if (!$strType) {
                    continue;
                }
                if (!in_array($strType, $arrCategories)) {
                    $arrCategories[] = trim($strType);
                }
            }
        }

        $objMicrodata = \Database::getInstance()->prepare('SELECT DISTINCT (type) FROM tl_microdata')->execute();
        while ($objMicrodata->next()) {
            if (!$objMicrodata->type) {
                continue;
            }
            if (!in_array($objMicrodata->type, $arrCategories)) {
                $arrCategories[] = trim($objMicrodata->type);
            }
        }

        \Database::getInstance()->prepare('UPDATE tl_ps_categories %s')->set(['tstamp' => time(), 'exist' => ''])->execute();

        foreach ($arrCategories as $strCategory) {

            $arrSet = [
                'exist' => '1',
                'tstamp' => time(),
                'category' => $strCategory
            ];

            $objCategory = \Database::getInstance()->prepare('SELECT * FROM tl_ps_categories WHERE category=?')->limit(1)->execute($strCategory);

            if ($objCategory->numRows) {
                \Database::getInstance()->prepare('UPDATE tl_ps_categories %s WHERE id=?')->set($arrSet)->execute($objCategory->id);
            } else {
                \Database::getInstance()->prepare('INSERT INTO tl_ps_categories %s')->set($arrSet)->execute();
            }
        }
    }
}