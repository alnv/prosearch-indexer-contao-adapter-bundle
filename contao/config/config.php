<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Modules\ElasticsearchTypeAheadModule;
use Alnv\ProSearchIndexerContaoAdapterBundle\Modules\ElasticsearchModule;
use Alnv\ProSearchIndexerContaoAdapterBundle\Purge\PurgeElasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\MicrodataModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\JobPosting;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Article;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Dataset;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\FAQPage;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Product;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Person;
use Alnv\ProSearchIndexerContaoAdapterBundle\MicroData\Event;
use Contao\ArrayUtil;

/**
 * Microdata
 */
$GLOBALS['PS_MICRODATA_CLASSES'] = [];
$GLOBALS['PS_MICRODATA_CLASSES']['Event'] = Event::class;
$GLOBALS['PS_MICRODATA_CLASSES']['Person'] = Person::class;
$GLOBALS['PS_MICRODATA_CLASSES']['Product'] = Product::class;
$GLOBALS['PS_MICRODATA_CLASSES']['FAQPage'] = FAQPage::class;
$GLOBALS['PS_MICRODATA_CLASSES']['Article'] = Article::class;
$GLOBALS['PS_MICRODATA_CLASSES']['Dataset'] = Dataset::class;
$GLOBALS['PS_MICRODATA_CLASSES']['JobPosting'] = JobPosting::class;

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_microdata'] = MicrodataModel::class;
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
        'elasticsearch_type_ahead' => ElasticsearchTypeAheadModule::class,
        'elasticsearch' => ElasticsearchModule::class
    ]
]);