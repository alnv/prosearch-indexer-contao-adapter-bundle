<?php

$GLOBALS['TL_DCA']['tl_indices'] = [
    'config' => [
        'dataContainer' => 'Table',
        'sql' => [
            'keys' => [
                'id' => 'primary'
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
        'types' => [
            'sql' => "blob NULL"
        ],
        'title' => [
            'sql' => "text NULL"
        ],
        'description' => [
            'sql' => "text NULL"
        ],
        'url' => [
            'sql' => "text NULL"
        ],
        'images' => [
            'sql' => "blob NULL"
        ],
        'document' => [
            'sql' => "longblob NULL"
        ],
        'last_indexed' => [
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'state' => [
            'sql' => "varchar(16) NOT NULL default ''"
        ]
    ]
];