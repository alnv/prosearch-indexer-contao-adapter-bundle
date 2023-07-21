<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Modules\ElasticsearchModule;

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_microdata'] = MicrodataModel::class;
$GLOBALS['TL_MODELS']['tl_indices'] = IndicesModel::class;

/**
 * Backend modules
 */
array_insert($GLOBALS['BE_MOD'], 3, [
    'prosearch-modules' => [
        'searchcredentials' => [
            'name' => 'searchcredentials',
            'tables' => [
                'tl_search_credentials'
            ]
        ],
        'synonyms' => [
            'name' => 'synonyms',
            'tables' => [
                'tl_synonyms'
            ]
        ]
    ]
]);

/**
 * Frontend modules
 */
array_insert($GLOBALS['FE_MOD'], 3, [
    'prosearch-modules' => [
        'elasticsearch' => ElasticsearchModule::class
    ]
]);