<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addLegend('ps_search_legend', 'global_legend')
    ->addField('psAnalyzer', 'ps_search_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('root', 'tl_page')
    ->applyToPalette('rootfallback', 'tl_page');

PaletteManipulator::create()
    ->addLegend('ps_search_legend', 'chmod_legend')
    ->addField('psSearchCategory', 'ps_search_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('regular', 'tl_page');

$GLOBALS['TL_DCA']['tl_page']['fields']['psSearchCategory'] = [
    'inputType' => 'text',
    'default' => 'page',
    'eval' => [
        'maxlength' => 128,
        'tl_class' => 'w50',
    ],
    'save_callback' => [function($strValue) {
        if (!$strValue) {
            return '';
        }
        $strValue = strtolower($strValue);
        $strValue = str_replace('-', '', $strValue);
        $strValue = str_replace('_', '', $strValue);
        $strValue = str_replace('.', '', $strValue);
        return str_replace(' ', '', $strValue);
    }],
    'sql' => "varchar(128) NOT NULL default 'page'"
];

$GLOBALS['TL_DCA']['tl_page']['fields']['psAnalyzer'] = [
    'inputType' => 'select',
    'default' => 'contao',
    'eval' => [
        'chosen' => true,
        'tl_class' => 'w50'
    ],
    'options_callback' => function() {
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