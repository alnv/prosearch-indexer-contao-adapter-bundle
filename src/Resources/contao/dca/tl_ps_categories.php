<?php

$GLOBALS['TL_DCA']['tl_ps_categories'] = [
    'config' => [
        'dataContainer' => 'Table',
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'category' => 'index'
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
        'category' => [
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'exist' => [
            'sql' => "char(1) NOT NULL default ''"
        ]
    ]
];