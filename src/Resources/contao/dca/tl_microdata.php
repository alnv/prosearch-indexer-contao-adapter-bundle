<?php

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_microdata'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_indices',
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index'
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
        'pid' => [
            'sql' => ['type' => 'integer','notnull' => false,'unsigned' => true,'default' => 0]
        ],
        'type' => [
            'sql' => "varchar(32) NOT NULL default ''"
        ],
        'data' => [
            'sql' => "longblob NULL"
        ],
        'checksum' => [
            'sql' => "text NULL"
        ]
    ]
];