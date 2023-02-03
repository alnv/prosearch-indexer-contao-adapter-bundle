<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'psEnableSearchCategories';
$GLOBALS['TL_DCA']['tl_module']['palettes']['prosearch'] = '{title_legend},name,headline,type;{search_legend},psEnableSearchCategories;{template_legend:hide},customTpl;{protected_legend:hide:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['psEnableSearchCategories'] = 'psSearchCategoriesOptions';

$GLOBALS['TL_DCA']['tl_module']['fields']['psEnableSearchCategories'] = [
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'clr',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['psSearchCategoriesOptions'] = [
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'tl_class' => 'w50 clr',
        'columnFields' => [
            'types' => [
                'label' => &$GLOBALS['TL_LANG']['tl_module']['psSearchCategoriesOptionsTypes'],
                'inputType' => 'select',
                'eval' => [
                    'chosen' => true,
                    'multiple' => true,
                    'style' => 'width:300px',
                    'includeBlankOption' => true,
                ],
                'options_callback' => function() {
                    return (new Categories())->getCategories();
                }
            ],
            'name' => [
                'label' => &$GLOBALS['TL_LANG']['tl_module']['psSearchCategoriesOptionsName'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['style' => 'width:200px'],
            ]
        ]
    ],
    'sql' => 'blob NULL',
];