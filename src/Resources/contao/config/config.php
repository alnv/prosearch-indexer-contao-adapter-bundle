<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
# use Alnv\ProSearchIndexerContaoAdapterBundle\Modules\ElasticsearchTypeAheadModule;
# use Alnv\ProSearchIndexerContaoAdapterBundle\Modules\ElasticsearchModule;
use Alnv\ProSearchIndexerContaoAdapterBundle\Purge\PurgeElasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Backend;
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
        ],
        'indices' => [
            'name' => 'indices',
            'reindexIndex' => [Backend::class, 'reIndexSite'],
            'tables' => [
                'tl_indices'
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
 * Adapter
 */
$GLOBALS['PS_ADAPTER'] = [[
    'type' => 'elasticsearch',
    'class' => Elasticsearch::class
]];