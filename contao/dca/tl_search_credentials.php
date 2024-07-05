<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Authorization;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Signature;
use Contao\Database;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;

$GLOBALS['TL_DCA']['tl_search_credentials'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'onload_callback' => [
            function () {
                $strRequestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
                $objEntity = Database::getInstance()->prepare('SELECT * FROM tl_search_credentials ORDER BY id DESC')->limit(1)->execute();
                if ($objEntity->numRows) {
                    if (!$objEntity->signature) {
                        Database::getInstance()->prepare('UPDATE tl_search_credentials %s WHERE id=?')->set([
                            'signature' => Signature::generate()
                        ])->limit(1)->execute($objEntity->id);
                    }
                    if (!Input::get('act') && !Input::get('id')) {
                        $this->redirect($this->addToUrl('act=edit&id=' . $objEntity->id . '&rt=' . $strRequestToken));
                    }
                } else {
                    if (!Input::get('act')) {
                        $this->redirect($this->addToUrl('act=create' . '&rt=' . $strRequestToken));
                    }
                }
            }
        ],
        'onsubmit_callback' => [function (DataContainer $dataContainer) {
            switch ($dataContainer->activeRecord->type) {
                case 'elasticsearch':
                case 'elasticsearch_cloud':
                    $objElasticsearchAdapter = new Elasticsearch((new Options())->getOptions());
                    $objElasticsearchAdapter->connect();
                    if (!$objElasticsearchAdapter->getClient()) {
                        Message::addError('No connection to the server could be established');
                    }
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
            'mode' => 0
        ],
        'label' => [
            'fields' => ['type'],
            'showColumns' => true
        ],
        'operations' => [
            'edit' => [
                'icon' => 'header.svg',
                'href' => 'act=edit'
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
        '__selector__' => ['type', 'singleDocument'],
        'default' => 'signature;type',
        'licence' => 'signature;type;authToken,keys;singleDocument'
    ],
    'subpalettes' => [
        'singleDocument' => 'analyzer'
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]
        ],
        'tstamp' => [
            'flag' => 6,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'signature' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 32,
                'tl_class' => 'w50',
                'mandatory' => true,
                'readonly' => true
            ],
            'sql' => "varchar(32) NOT NULL default ''"
        ],
        'type' => [
            'inputType' => 'select',
            'eval' => [
                'maxlength' => 32,
                'tl_class' => 'w50',
                'submitOnChange' => true,
                'includeBlankOption' => true
            ],
            'options' => ['licence'],
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
                'mandatory' => false
            ],
            'sql' => "varchar(128) NOT NULL default ''"
        ],
        'singleDocument' => [
            'inputType' => 'checkbox',
            'eval' => [
                'tl_class' => 'clr',
                'submitOnChange' => true
            ],
            'sql' => "char(1) NOT NULL default ''"
        ],
        'password' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 128,
                'tl_class' => 'w50',
                'mandatory' => false,
                'decodeEntities' => true
            ],
            'sql' => "varchar(128) NOT NULL default ''"
        ],
        'key' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'mandatory' => true,
                'tl_class' => 'w50 clr',
                'decodeEntities' => true
            ],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'authToken' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'mandatory' => true,
                'tl_class' => 'long clr',
                'decodeEntities' => true
            ],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'keys' => [
            'inputType' => 'multiColumnWizard',
            'eval' => [
                'mandatory' => true,
                'tl_class' => 'long clr',
                'decodeEntities' => true,
                'columnFields' => [
                    'key' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_search_credentials']['key'],
                        'inputType' => 'text',
                        'eval' => ['style' => 'width:100%']
                    ],
                    'domain' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_search_credentials']['domain'],
                        'inputType' => 'text',
                        'eval' => ['style' => 'width:100%'],
                        'save_callback' => [[Authorization::class, 'parseDomain']]
                    ]
                ]
            ],
            'save_callback' => [function ($varValue, DataContainer $dc) {
                $arrKeys = StringUtil::deserialize($varValue, true);
                foreach ($arrKeys as $arrKey) {
                    $strKey = (new Authorization())->encodeLicense(($arrKey['key'] ?? ''), ($arrKey['domain'] ?? ''), $dc->activeRecord->authToken);
                    $blnValid = (new Authorization())->isValid($strKey);
                    if (!$blnValid) {
                        throw new Exception('Licence for domain ' . ($arrKey['domain'] ?? '') . ' is invalid! You can purchase a licence at this address: https://app.sineos.de');
                    }
                }
                return $varValue;
            }],
            'sql' => "blob NULL"
        ],
        'cert' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'tl_class' => 'w50'
            ],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'analyzer' => [
            'inputType' => 'select',
            'default' => 'contao',
            'eval' => [
                'chosen' => true,
                'tl_class' => 'w50',
                'mandatory' => true,
                'includeBlankOption' => true
            ],
            'options_callback' => function() {
                $objAdapter = new Elasticsearch((new Options())->getOptions());
                $arrAnalyzer = array_keys($objAdapter->getAnalyzer());
                $arrAnalyzer[] = 'whitespace';
                $arrAnalyzer[] = 'standard';
                $arrAnalyzer[] = 'keyword';
                $arrAnalyzer[] = 'simple';
                $arrAnalyzer[] = 'stop';
                return $arrAnalyzer;
            },
            'reference' => &$GLOBALS['TL_LANG']['MSC']['psAnalyzer'],
            'sql' => "varchar(64) NOT NULL default 'contao'"
        ]
    ]
];