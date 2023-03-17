<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use \Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'psAutoCompletionType';

$GLOBALS['TL_DCA']['tl_module']['palettes']['elasticsearch'] = '{title_legend},name,headline,type;{search_legend},psAutoCompletionType,psAnalyzer,psSearchCategories,perPage;{redirect_legend:hide},jumpTo;{template_legend:hide},customTpl,psAutoCompletionTemplate;{protected_legend:hide:hide},protected;{expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['tl_module']['subpalettes']['psAutoCompletionType_simple'] = '';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['psAutoCompletionType_advanced'] = '';

$GLOBALS['TL_DCA']['tl_module']['fields']['psSearchCategories'] = [
    'inputType' => 'select',
    'eval' => [
        'chosen' => true,
        'multiple' => true,
        'tl_class' => 'w50'
    ],
    'options_callback' => function() {
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
    'reference' => &$GLOBALS['TL_LANG']['tl_module'],
    'options' => ['simple', 'advanced'],
    'sql' => "varchar(32) NOT NULL default 'advanced'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psAutoCompletionTemplate'] = [
    'inputType' => 'select',
    'eval' => [
        'chosen' => true,
        'tl_class' => 'w50'
    ],
    'options_callback' => function() {
        return $this->getTemplateGroup('ps_search_result');
    },
    'sql' => "varchar(64) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psAnalyzer'] = [
    'inputType' => 'select',
    'default' => 'standard',
    'eval' => [
        'chosen' => true,
        'tl_class' => 'w50'
    ],
    'options_callback' => function() {

        $objAdapter = new Elasticsearch();
        $arrAnalyzer = array_keys($objAdapter->getAnalyzer());
        $arrAnalyzer[] = 'standard';

        return $arrAnalyzer;
    },
    'sql' => "varchar(64) NOT NULL default 'standard'"
];