<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Stats;
use Contao\DC_Table;
use Contao\Input;

$GLOBALS['TL_DCA']['tl_search_stats'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        'onload_callback' => [function () {
            if (!Input::get('export')) {
                return;
            }
            Stats::export();
        }],
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'keywords' => 'index'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => 2,
            'flag' => 12,
            'fields' => ['count'],
            'panelLayout' => 'filter;sort,search'
        ],
        'label' => [
            'fields' => ['keywords', 'types', 'count', 'hits', 'clicks'],
            'showColumns' => true
        ],
        'global_operations' => [
            'export' => [
                'icon' => 'theme_import.svg',
                'href' => 'export=all',
                'label' => &$GLOBALS['TL_LANG']['tl_search_stats']['export'],
                'attributes' => 'onclick="Backend.getScrollOffset()"'
            ]
        ],
        'operations' => [
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"'
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg'
            ]
        ]
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]
        ],
        'tstamp' => [
            'flag' => 6,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'keywords' => [
            'search' => true,
            'sql' => "varchar(128) NOT NULL default ''"
        ],
        'types' => [
            'search' => true,
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'hits' => [
            'flag' => 12,
            'sorting' => true,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'count' => [
            'flag' => 12,
            'sorting' => true,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'clicks' => [
            'flag' => 12,
            'sorting' => true,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'urls' => [
            'search' => true,
            'sql' => 'text NULL'
        ],
        'source' => [
            'sql' => 'text NULL'
        ]
    ]
];