<?php

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_synonyms'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => 1,
            'flag' => 1,
            'fields' => ['keyword'],
            'panelLayout' => 'search,limit'
        ],
        'label' => [
            'fields' => ['keyword']
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            ]
        ],
        'operations' => [
            'edit' => [
                'icon' => 'edit.svg',
                'href' => 'act=edit'
            ],
            'copy' => [
                'href' => 'act=copy',
                'icon' => 'copy.svg'
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"'
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ]
        ]
    ],
    'palettes' => [
        'default' => 'keyword,synonyms,disable'
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]
        ],
        'tstamp' => [
            'flag' => 6,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'keyword' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'tl_class' => 'w50',
                'mandatory' => true
            ],
            'search' => true,
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'synonyms' => [
            'inputType' => 'listWizard',
            'eval' => [
                'tl_class' => 'clr',
                'mandatory' => true
            ],
            'sql' => "text NULL"
        ],
        'disable' => [
            'inputType' => 'checkbox',
            'eval' => [
                'tl_class' => 'clr',
                'decodeEntities' => true
            ],
            'sql' => "char(1) NOT NULL default ''"
        ]
    ]
];