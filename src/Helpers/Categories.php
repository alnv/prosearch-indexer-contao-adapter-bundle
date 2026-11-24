<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\Database;
use Contao\PageModel;
use Contao\StringUtil;

class Categories
{
    public static function normalize(string $category): string
    {
        $category = mb_strtolower(trim(StringUtil::decodeEntities($category)));

        return (string) preg_replace('/[^\p{L}\p{N}_-]+/u', '', $category);
    }

    public static function normalizeList(string $categories): array
    {
        $list = [];

        foreach (explode(',', $categories) as $category) {
            $category = self::normalize($category);

            if ('' !== $category && !\in_array($category, $list, true)) {
                $list[] = $category;
            }
        }

        return $list;
    }

    public function getCategories(): array
    {

        $this->setCategories();

        $arrCategories = [];
        $objCategories = Database::getInstance()->prepare("SELECT category FROM tl_ps_categories WHERE category!='' ORDER BY category")->execute();

        while ($objCategories->next()) {
            $arrCategories[] = $objCategories->category;
        }

        return $arrCategories;
    }

    public function getTranslatedCategories($strRootId = ''): array
    {

        $arrCategories = [];
        $strCurrentLanguage = $GLOBALS['TL_LANGUAGE'] ?? '';

        if ($strRootId && ($objRootPage = PageModel::findByPk($strRootId))) {
            $strCurrentLanguage = $objRootPage->language;
        }

        if (!$strCurrentLanguage) {
            $objRootPage = Database::getInstance()->prepare("SELECT language FROM tl_page WHERE (type=? OR type=?) AND `language`!='' ORDER BY type='rootfallback' DESC, sorting")->limit(1)->execute('rootfallback', 'root');
            $strCurrentLanguage = (string) $objRootPage->language;
        }

        $objCategories = Database::getInstance()->prepare("SELECT id, category, translating FROM tl_ps_categories WHERE exist='1' AND category!='' ORDER BY category")->execute();

        while ($objCategories->next()) {
            $arrCategories[$objCategories->category] = [
                'id' => $objCategories->id,
                'key' => $objCategories->category,
                'label' => self::translate($objCategories->category, $objCategories->translating, (string) $strCurrentLanguage)
            ];
        }

        return $arrCategories;
    }

    public static function translate(string $category, mixed $translations, string $language): string
    {
        $language = self::normalizeLanguage($language);
        $primary = explode('_', $language)[0];
        $exact = $fallback = '';

        foreach (StringUtil::deserialize($translations, true) as $translation) {
            if (!\is_array($translation)) {
                continue;
            }

            $label = trim(StringUtil::decodeEntities((string) ($translation['label'] ?? '')));
            $translationLanguage = self::normalizeLanguage((string) ($translation['language'] ?? ''));

            if ('' === $label || '' === $translationLanguage) {
                continue;
            }

            if ($translationLanguage === $language) {
                $exact = $label;
            } elseif ('' === $fallback && explode('_', $translationLanguage)[0] === $primary) {
                $fallback = $label;
            }
        }

        return $exact ?: ($fallback ?: $category);
    }

    public function setCategories(): array
    {

        $result = ['created' => 0, 'activated' => 0, 'deactivated' => 0];
        $used = [];

        $objIndices = Database::getInstance()->prepare('SELECT DISTINCT types FROM tl_indices WHERE state=?')->execute(States::ACTIVE);

        while ($objIndices->next()) {
            foreach (StringUtil::deserialize($objIndices->types, true) as $strType) {
                $strType = self::normalize((string) $strType);

                if ('' !== $strType) {
                    $used[$strType] = true;
                }
            }
        }

        $existing = [];
        $objCategories = Database::getInstance()->prepare('SELECT id, category, exist FROM tl_ps_categories')->execute();
        while ($objCategories->next()) {
            $existing[$objCategories->category] = ['id' => (int) $objCategories->id, 'exist' => '1' === (string) $objCategories->exist];
        }

        foreach ($existing as $category => $row) {
            $inUse = isset($used[$category]);

            if ($inUse !== $row['exist']) {
                Database::getInstance()->prepare('UPDATE tl_ps_categories %s WHERE id=?')->set(['tstamp' => time(), 'exist' => $inUse ? '1' : ''])->execute($row['id']);
                ++$result[$inUse ? 'activated' : 'deactivated'];
            }
        }

        foreach (array_keys($used) as $category) {
            if (!isset($existing[$category])) {
                Database::getInstance()->prepare('INSERT INTO tl_ps_categories %s')->set(['tstamp' => time(), 'category' => $category, 'exist' => '1'])->execute();
                ++$result['created'];
            }
        }

        return $result;
    }

    private static function normalizeLanguage(string $language): string
    {
        return strtolower(str_replace('-', '_', trim($language)));
    }
}