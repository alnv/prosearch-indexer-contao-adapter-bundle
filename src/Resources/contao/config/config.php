<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Modules\ElasticsearchTypeAheadModule;
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
        'categories' => [
            'name' => 'categories',
            'tables' => [
                'tl_ps_categories'
            ]
        ],
        'synonyms' => [
            'name' => 'synonyms',
            'tables' => [
                'tl_synonyms'
            ]
        ],
        'stats' => [
            'name' => 'stats',
            'tables' => [
                'tl_search_stats'
            ]
        ]
    ]
]);

/**
 * Frontend modules
 */
array_insert($GLOBALS['FE_MOD'], 3, [
    'prosearch-modules' => [
        'elasticsearch_type_ahead' => ElasticsearchTypeAheadModule::class,
        'elasticsearch' => ElasticsearchModule::class
    ]
]);