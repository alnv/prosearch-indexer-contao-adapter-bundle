<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Backend;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Modules\ElasticsearchModule;
use Alnv\ProSearchIndexerContaoAdapterBundle\Modules\ElasticsearchTypeAheadModule;
use Alnv\ProSearchIndexerContaoAdapterBundle\Modules\SearchStatsDashboardModule;
use Alnv\ProSearchIndexerContaoAdapterBundle\Purge\PurgeElasticsearch;
use Contao\ArrayUtil;

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_indices'] = IndicesModel::class;

/**
 * Backend modules
 */
ArrayUtil::arrayInsert($GLOBALS['BE_MOD'], 3, [
    'prosearch-modules' => [
        'searchdashboard' => [
            'name' => 'searchdashboard',
            'callback' => SearchStatsDashboardModule::class
        ],
        'indices' => [
            'name' => 'indices',
            'reindexIndex' => [Backend::class, 'reIndexSite'],
            'tables' => [
                'tl_indices'
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
        'searchcredentials' => [
            'name' => 'searchcredentials',
            'tables' => [
                'tl_search_credentials'
            ]
        ]
    ]
]);

/**
 * Purge settings
 */
$GLOBALS['TL_PURGE']['custom']['deleteElasticsearchIndex'] = [
    'callback' => [PurgeElasticsearch::class, 'deleteAllDatabases']
];

/**
 * Frontend modules
 */
ArrayUtil::arrayInsert($GLOBALS['FE_MOD'], 3, [
    'prosearch-modules' => [
        'prosearch_type_ahead' => ElasticsearchTypeAheadModule::class,
        'prosearch' => ElasticsearchModule::class
    ]
]);