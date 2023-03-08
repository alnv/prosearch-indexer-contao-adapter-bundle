<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Models\DocumentsModels;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Modules\ProsearchModule;

/**
 * Models
 */
$GLOBALS['TL_MODELS'] = [
    'tl_documents' => DocumentsModels::class,
    'tl_microdata' => MicrodataModel::class,
    'tl_indices' => IndicesModel::class
];

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
        'prosearch' => ProsearchModule::class
    ]
]);

// dev
// (new \Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch())->indexDocuments();exit;
$GLOBALS['TL_CRON']['minutely'][] = [\Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch::class, 'indexDocuments'];