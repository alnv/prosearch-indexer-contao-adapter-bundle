<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\MappingFactory;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Contao\Message;
use Contao\System;

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
    'save_callback' => [static fn ($strValue): string => implode(',', Categories::normalizeList((string) $strValue))],
    'sql' => "varchar(128) NOT NULL default 'page'"
];

$GLOBALS['TL_DCA']['tl_page']['fields']['psAnalyzer'] = [
    'inputType' => 'select',
    'default' => 'contao',
    'eval' => [
        'chosen' => true,
        'tl_class' => 'w50'
    ],
    // analyzers for full text ("keyword" was removed: it makes the whole text a single term)
    'options_callback' => static function (): array {
        $arrAnalyzer = array_keys(System::getContainer()->get(MappingFactory::class)->getAnalyzers());

        return array_values(array_unique([...$arrAnalyzer, 'standard', 'simple', 'whitespace', 'stop']));
    },
    // the analyzer is part of the index mapping: a change needs a rebuild of the index
    'save_callback' => [static function ($value, DataContainer $dc) {
        if ($dc->activeRecord && (string) $value !== (string) $dc->activeRecord->psAnalyzer) {
            Message::addInfo(sprintf($GLOBALS['TL_LANG']['tl_page']['psAnalyzerRebuild'] ?? 'The analyzer takes effect after rebuilding the index: vendor/bin/contao-console prosearch:index:rebuild --root=%s --force', $dc->id));
        }

        return $value;
    }],
    'reference' => &$GLOBALS['TL_LANG']['MSC']['psAnalyzer'],
    'sql' => "varchar(64) NOT NULL default 'contao'"
];