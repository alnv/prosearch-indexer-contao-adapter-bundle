<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = '';
$GLOBALS['TL_DCA']['tl_module']['palettes']['prosearch'] = '{title_legend},name,headline,type;{search_legend},psSearchCategories,perPage;{redirect_legend:hide},jumpTo;{template_legend:hide},customTpl;{protected_legend:hide:hide},protected;{expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['tl_module']['fields']['psSearchCategories'] = [
    'inputType' => 'select',
    'eval' => [
        'chosen' => true,
        'multiple' => true,
        'tl_class' => 'long clr'
    ],
    'options_callback' => function() {
        return (new Categories())->getCategories();
    },
    'sql' => 'blob NULL',
];