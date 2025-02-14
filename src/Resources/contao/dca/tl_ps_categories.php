<?php

use Contao\System;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_ps_categories'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'category' => 'index'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => 0,
            'fields' => ['category'],
            'panelLayout' => 'filter,limit'
        ],
        'label' => [
            'fields' => ['category', 'exist'],
            'showColumns' => true
        ],
        'operations' => [
            'edit' => [
                'icon' => 'edit.svg',
                'href' => 'act=edit'
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm']??'') . '\'))return false;Backend.getScrollOffset()"'
            ]
        ]
    ],
    'palettes' => [
        'default' => 'category;translating'
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]
        ],
        'tstamp' => [
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'category' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 128,
                'readonly' => true,
                'tl_class' => 'long clr',
                'mandatory' => true
            ],
            'filter' => true,
            'sql' => "varchar(128) NOT NULL default ''"
        ],
        'translating' => [
            'inputType' => 'multiColumnWizard',
            'eval' => [
                'tl_class' => 'w50 clr',
                'columnFields' => [
                    'language' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_ps_categories']['language'],
                        'inputType' => 'select',
                        'eval' => [
                            'chosen' => true,
                            'style' => 'width:250px',
                            'includeBlankOption' => true
                        ],
                        'options_callback' => function() {
                            return System::getContainer()->get('contao.intl.locales')->getLocales(null, false);
                        }
                    ],
                    'label' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_ps_categories']['label'],
                        'inputType' => 'text',
                        'eval' => [
                            'style' => 'width:250px'
                        ]
                    ]
                ]
            ],
            'sql' => 'blob NULL'
        ],
        'exist' => [
            'inputType' => 'checkbox',
            'eval' => [
                'multiple' => false
            ],
            'filter' => true,
            'sql' => "char(1) NOT NULL default ''"
        ]
    ]
];