<?php

use \Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addLegend('ps_search_legend', 'global_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('psAnalyzer', 'ps_search_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('root', 'tl_page')
    ->applyToPalette('rootfallback', 'tl_page');

$GLOBALS['TL_DCA']['tl_page']['fields']['psAnalyzer'] = [
    'inputType' => 'select',
    'default' => 'contao',
    'eval' => [
        'chosen' => true,
        'tl_class' => 'w50'
    ],
    'options_callback' => function() {
        $objAdapter = new Elasticsearch();
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