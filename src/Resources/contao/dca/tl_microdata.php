<?php

$GLOBALS['TL_DCA']['tl_microdata'] = [
    'config' => [
        'dataContainer' => 'Table',
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
            'flag' => 6,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'pid' => [
            'sql' => ['type' => 'integer','notnull' => false,'unsigned' => true,'default' => 0]
        ],
        'type' => [
            'sql' => "varchar(16) NOT NULL default ''"
        ],
        'data' => [
            'sql' => "longblob NULL"
        ],
        'checksum' => [
            'sql' => "text NULL"
        ]
    ]
];