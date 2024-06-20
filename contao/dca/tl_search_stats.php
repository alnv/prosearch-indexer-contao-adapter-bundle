<?php

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_search_stats'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
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
            'panelLayout' => 'filter;sort,search'
        ],
        'label' => [
            'fields' => ['keywords', 'types', 'count', 'clicks', 'hits'],
            'showColumns' => true
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
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
            'sql' => 'text NULL'
        ]
    ]
];