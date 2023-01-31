<?php

$GLOBALS['TL_DCA']['tl_search_credentials'] = [
    'config' => [
        'dataContainer' => 'Table',
        'enableVersioning' => true,
        'sql'=> [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => 0,
            'panelLayout' => 'filter;sort,search,limit'
        ],
        'label' => [
            'fields' => ['type'],
            'showColumns' => true
        ],
        'global_operations' => [],
        'operations' => [
            'edit' => [
                'icon' => 'header.svg',
                'href' => 'act=edit'
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ]
        ]
    ],
    'palettes'=> [
        '__selector__' => ['type'],
        'default' => 'type',
        'licence' => 'type,key',
        'self' => 'type,host,port,username,password'
    ],
    'fields' => [
        'id' => [
            'sql' => ['type'=>'integer','autoincrement'=>true,'notnull'=>true,'unsigned'=>true]
        ],
        'tstamp' => [
            'flag' => 6,
            'sql' => ['type'=>'integer','notnull'=>false,'unsigned'=>true,'default' => 0]
        ],
        'type' => [
            'inputType' => 'select',
            'eval' => [
                'maxlength' => 16,
                'tl_class' => 'w50',
                'submitOnChange' => true,
                'includeBlankOption' => true
            ],
            'options' => ['self', 'licence'],
            'reference' => &$GLOBALS['TL_LANG']['tl_search_credentials'],
            'sql' => "varchar(16) NOT NULL default ''"
        ],
        'host' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 64,
                'tl_class' => 'w50',
                'mandatory' => true
            ],
            'sql' => "varchar(64) NOT NULL default ''"
        ],
        'port' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 16,
                'tl_class' => 'w50',
                'mandatory' => false
            ],
            'sql' => "varchar(16) NOT NULL default ''"
        ],
        'username' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 128,
                'tl_class' => 'w50',
                'mandatory' => true
            ],
            'sql' => "varchar(128) NOT NULL default ''"
        ],
        'password' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 128,
                'tl_class' => 'w50',
                'mandatory' => true
            ],
            'sql' => "varchar(128) NOT NULL default ''"
        ],
        'key' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 64,
                'tl_class' => 'w50',
                'mandatory' => true
            ],
            'sql' => "varchar(64) NOT NULL default ''"
        ]
    ]
];