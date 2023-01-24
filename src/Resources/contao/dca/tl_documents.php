<?php

$GLOBALS['TL_DCA']['tl_documents'] = [
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
        'pid' => [
            'sql' => ['type' => 'integer','notnull' => false,'unsigned' => true,'default' => 0]
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
        'tags' => [
            'sql' => "text NULL"
        ],
        'url' => [
            'sql' => "text NULL"
        ],
        'document' => [
            'sql' => "longblob NULL"
        ]
    ]
];