<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Search\ElasticsearchAdapter;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_search_credentials'] = [
    'config' => [
        'dataContainer' => 'Table',
        'enableVersioning' => true,
        'onload_callback' => [
            function () {
                $objEntity = \Database::getInstance()->prepare('SELECT * FROM tl_search_credentials ORDER BY id DESC')->limit(1)->execute();

                if ($objEntity->numRows) {
                    if (!\Input::get('act') && !\Input::get('id')) {
                        $this->redirect($this->addToUrl('act=edit&id=' . $objEntity->id . '&rt=' . REQUEST_TOKEN));
                    }
                } else {
                    if (!\Input::get('act')) {
                        $this->redirect($this->addToUrl('act=create'));
                    }
                }
            }
        ],
        'onsubmit_callback' => [function (DataContainer $dataContainer) {
            switch ($dataContainer->activeRecord->type) {
                case 'licence':
                case 'elasticsearch':
                case 'elasticsearch_cloud':
                    $varConnect = (new ElasticsearchAdapter())->connect();
                    break;
            }
        }],
        'sql' => [
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
    'palettes' => [
        '__selector__' => ['type'],
        'default' => 'type',
        'licence' => 'type,key',
        'elasticsearch' => 'type,host,port,username,password',
        'elasticsearch_cloud' => 'type,cloudid,key'
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]
        ],
        'tstamp' => [
            'flag' => 6,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'type' => [
            'inputType' => 'select',
            'eval' => [
                'maxlength' => 32,
                'tl_class' => 'w50',
                'submitOnChange' => true,
                'includeBlankOption' => true
            ],
            'options' => ['licence', 'elasticsearch', 'elasticsearch_cloud'],
            'reference' => &$GLOBALS['TL_LANG']['tl_search_credentials'],
            'sql' => "varchar(32) NOT NULL default ''"
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
                'mandatory' => true,
                'decodeEntities' => true
            ],
            'sql' => "varchar(128) NOT NULL default ''"
        ],
        'key' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'tl_class' => 'w50',
                'mandatory' => true,
                'decodeEntities' => true
            ],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'cloudid' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'tl_class' => 'w50',
                'mandatory' => true,
                'decodeEntities' => true
            ],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'cert' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'tl_class' => 'w50'
            ],
            'sql' => "varchar(255) NOT NULL default ''"
        ]
    ]
];