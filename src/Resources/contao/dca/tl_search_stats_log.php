<?php

use Contao\DC_Table;

/**
 * Search statistics: one row per front end search (Stats\SearchStatsLogger). Replaces tl_search_stats since 4.0;
 * the old per-term counters are expanded into single rows by Migration\MergeSearchStatsMigration (legacy = '1').
 *
 * Back end module "Suchprotokoll" (do=stats): list, single delete, CSV export and "clear" (key callbacks in
 * Stats\SearchStatsLogActions, protected by the request token). Analytics: back end module "Dashboard".
 */
$GLOBALS['TL_DCA']['tl_search_stats_log'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'backendSearchIgnore' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                // Covering indexes: time ranges (dashboard periods, back end list) and grouping per term
                'tstamp,keyword,hits,clicks,legacy' => 'index',
                'keyword,tstamp,hits,clicks' => 'index'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => 2,
            'fields' => ['tstamp DESC', 'id DESC'],
            'panelLayout' => 'filter;search,sort,limit'
        ],
        'label' => [
            'fields' => ['tstamp', 'keyword', 'hits', 'clicks', 'source'],
            'showColumns' => true
        ],
        'global_operations' => [
            'export_terms' => [
                'href' => 'key=export&amp;mode=terms',
                'icon' => 'theme_export.svg'
            ],
            'export_log' => [
                'href' => 'key=export&amp;mode=log',
                'icon' => 'theme_export.svg'
            ],
            // Not "clear": the operation name becomes a CSS class, and Contao's ".clear" utility class hides the link
            'clear_log' => [
                'href' => 'key=clear',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['tl_search_stats_log']['clearConfirm'] ?? 'Wollen Sie das komplette Suchprotokoll löschen?') . '\'))return false"'
            ]
        ],
        'operations' => [
            'delete',
            'show'
        ]
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]
        ],
        'tstamp' => [
            'sorting' => true,
            'flag' => 8,
            'eval' => ['rgxp' => 'datim'],
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'keyword' => [
            'search' => true,
            'sorting' => true,
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'hits' => [
            'sorting' => true,
            'flag' => 12,
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'clicks' => [
            'sorting' => true,
            'flag' => 12,
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'urls' => [
            'eval' => ['multiple' => true],
            'sql' => 'text NULL'
        ],
        'types' => [
            'search' => true,
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'source' => [
            'search' => true,
            'sql' => "varchar(1024) NOT NULL default ''"
        ],
        'legacy' => [
            'filter' => true,
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default ''"
        ]
    ]
];
