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
            'flag' => 6,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
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
        ]
    ]
];