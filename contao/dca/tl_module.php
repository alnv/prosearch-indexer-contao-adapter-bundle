<?php

use Contao\CoreBundle\Util\LocaleUtil;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Contao\Environment;
use Contao\Database;

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'psAutoCompletionType';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'psUseOpenAi';

$GLOBALS['TL_DCA']['tl_module']['palettes']['elasticsearch_type_ahead'] = '{title_legend},name,headline,type;{search_legend},psAnalyzer,psLanguage,psDomains;psSearchCategories;psAutoCompletionType;minKeywordLength,perPage,fuzzy,psUseRichSnippets,psOpenDocumentInBrowser;{open_ai_legend},psUseOpenAi;{style_legend},psPreventCssLoading;{redirect_legend:hide},jumpTo;{template_legend:hide},customTpl,psResultsTemplate;{protected_legend:hide:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['elasticsearch'] = '{title_legend},name,headline,type;{search_legend},psAnalyzer,psLanguage,psDomains;psSearchCategories;minKeywordLength,perPage,fuzzy,psUseRichSnippets,psOpenDocumentInBrowser;{open_ai_legend},psUseOpenAi;{style_legend},psPreventCssLoading;{template_legend:hide},customTpl,psResultsTemplate;{protected_legend:hide:hide},protected;{expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['tl_module']['subpalettes']['psAutoCompletionType_simple'] = '';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['psAutoCompletionType_advanced'] = '';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['psUseOpenAi'] = 'psOpenAssistant,psOpenAiRelevance';

$GLOBALS['TL_DCA']['tl_module']['fields']['psSearchCategories'] = [
    'inputType' => 'checkboxWizard',
    'eval' => [
        'chosen' => true,
        'multiple' => true,
        'tl_class' => 'w50'
    ],
    'options_callback' => function () {
        return (new Categories())->getCategories();
    },
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psAutoCompletionType'] = [
    'inputType' => 'radio',
    'eval' => [
        'submitOnChange' => true,
        'tl_class' => 'clr'
    ],
    'default' => 'advanced',
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['psReference'],
    'options' => ['simple', 'advanced'],
    'sql' => "varchar(32) NOT NULL default 'advanced'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psResultsTemplate'] = [
    'inputType' => 'select',
    'eval' => [
        'chosen' => true,
        'tl_class' => 'w50'
    ],
    'options_callback' => function () {
        return $this->getTemplateGroup('elasticsearch_result');
    },
    'sql' => "varchar(64) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psUseRichSnippets'] = [
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'clr'
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psPreventCssLoading'] = [
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'clr'
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psLanguage'] = [
    'inputType' => 'text',
    'eval' => [
        'maxlength' => 64,
        'nospace' => true,
        'decodeEntities' => true,
        'tl_class' => 'w50'
    ],
    'save_callback' => [
        static function ($value) {
            if (!$value) {
                return '';
            }
            if (!preg_match('/^[a-z]{2,}/i', $value)) {
                throw new RuntimeException($GLOBALS['TL_LANG']['ERR']['language']);
            }
            return LocaleUtil::canonicalize($value);
        }
    ],
    'sql' => "varchar(64) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psDomains'] = [
    'inputType' => 'text',
    'default' => Environment::get('host'),
    'eval' => [
        'decodeEntities' => true,
        'tl_class' => 'clr long'
    ],
    'sql' => "blob NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psOpenDocumentInBrowser'] = [
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'clr'
    ],
    'sql' => "char(1) NOT NULL default '1'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psAnalyzer'] = [
    'inputType' => 'select',
    'default' => 'contao',
    'eval' => [
        'chosen' => true,
        'tl_class' => 'w50',
        'includeBlankOption' => true
    ],
    'options_callback' => function () {
        $objAdapter = new Elasticsearch((new Options())->getOptions());
        $arrAnalyzer = array_keys($objAdapter->getAnalyzer());
        $arrAnalyzer[] = 'whitespace';
        $arrAnalyzer[] = 'standard';
        $arrAnalyzer[] = 'keyword';
        $arrAnalyzer[] = 'simple';
        $arrAnalyzer[] = 'stop';
        return $arrAnalyzer;
    },
    'reference' => &$GLOBALS['TL_LANG']['MSC']['psAnalyzer'],
    'sql' => "varchar(64) NOT NULL default 'contao'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psOpenAssistant'] = [
    'inputType' => 'select',
    'eval' => [
        'chosen' => true,
        'tl_class' => 'w50',
        'includeBlankOption' => true
    ],
    'options_callback' => function () {
        $arReturn = [];
        if (Database::getInstance()->tableExists('tl_ai_assistants')) {
            $objAssistants = Database::getInstance()->prepare('SELECT * FROM tl_ai_assistants ORDER BY `name`')->execute();
            while ($objAssistants->next()) {
                $arReturn[] = $objAssistants->name;
            }
        }
        return $arReturn;
    },
    'sql' => "varchar(128) NOT NULL default ''"
];
$GLOBALS['TL_DCA']['tl_module']['fields']['psOpenAiRelevance'] = [
    'inputType' => 'text',
    'eval' => [
        'minval' => 0,
        'maxval' => 100,
        'tl_class' => 'w50'
    ],
    'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
];
$GLOBALS['TL_DCA']['tl_module']['fields']['psUseOpenAi'] = [
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'clr',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];