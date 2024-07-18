<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Elasticsearch;
use Alnv\ProSearchIndexerContaoAdapterBundle\Adapter\Options;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Toolkit;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;

$GLOBALS['TL_DCA']['tl_indices'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'onload_callback' => [function (DataContainer $objDataContainer) {

            $strAct = Input::get('act') ?: '';
            $blnIsSavin = Input::post('FORM_SUBMIT') === $objDataContainer->table;

            if ($strAct != 'edit') {
                return;
            }

            $objIndices = IndicesModel::findByPk($objDataContainer->id);
            if (!$objIndices) {
                return;
            }

            $varSettings = Input::post('settings') ?: $objIndices->settings;
            $arrSettings = StringUtil::deserialize($varSettings, true);

            if (!in_array('preventIndexMetadata', $arrSettings) && !in_array('preventIndex', $arrSettings)) {
                $GLOBALS['TL_DCA']['tl_indices']['fields']['title']['eval']['readonly'] = true;
                $GLOBALS['TL_DCA']['tl_indices']['fields']['description']['eval']['readonly'] = true;
                PaletteManipulator::create()->removeField('images')->applyToPalette('default', 'tl_indices');
            }

            $arrPalettes = [];
            $strFieldPrefix = 'indices_';

            foreach (Toolkit::parseDocumentIndex($objIndices->document) as $strField => $varData) {

                $strFieldname = $strFieldPrefix . $strField;

                $arrField = [
                    'label' => &$GLOBALS['TL_LANG']['tl_indices'][$strFieldname],
                    'inputType' => 'listWizard',
                    'eval' => [
                        'alwaysSave' => true,
                        'doNotSaveEmpty' => true,
                        'decodeEntities' => true,
                        'doNotCopy' => true,
                        'tl_class' => 'clr'
                    ],
                    'load_callback' => [function ($strValue, DataContainer $objDataContainer) {
                        return Input::post('__' . $objDataContainer->inputName . '__');
                    }],
                    'save_callback' => [function ($strValue, DataContainer $objDataContainer) {
                        Input::setPost($objDataContainer->inputName, StringUtil::deserialize($strValue, true));
                        return '';
                    }]
                ];

                if (!in_array('preventIndex', $arrSettings)) {
                    $arrField['eval']['readonly'] = true;
                }

                if ($blnIsSavin) {
                    unset($arrField['load_callback']);
                }

                if ($strField == 'text' || $strField == 'document') {
                    $arrField['inputType'] = 'textarea';
                    if (is_array($varData)) {
                        $varData = implode(' ', $varData);
                    }
                }

                Input::setPost('__' . $strFieldname . '__', $varData);

                $arrPalettes[] = $strFieldname;
                $GLOBALS['TL_DCA']['tl_indices']['fields'][$strFieldname] = $arrField;
            }

            $strPrevField = $arrPalettes[0] ?? '';
            $objPaletteManipulator = PaletteManipulator::create();
            $objPaletteManipulator->addLegend('document_legend');
            $objPaletteManipulator->addField($strPrevField, 'document_legend');

            foreach ($arrPalettes as $index => $strField) {

                if (!$index) {
                    continue;
                }

                $objPaletteManipulator->addField($strField, $strPrevField);
                $strPrevField = $strField;
            }

            $objPaletteManipulator->applyToPalette('default', 'tl_indices');
        }],
        'onsubmit_callback' => [function (DataContainer $objDataContainer) {

            $objIndices = IndicesModel::findByPk($objDataContainer->id);
            if (!$objIndices) {
                return;
            }

            $strFieldPrefix = 'indices_';
            $arrDocumentData = [];
            foreach (Toolkit::parseDocumentIndex($objIndices->document) as $strField => $varData) {
                $arrDocumentData[$strField] = Input::post($strFieldPrefix . $strField);
            }

            $objIndices->document = serialize($arrDocumentData);
            $objIndices->save();

            Message::addInfo('Änderungen werden erst bei einer Re-Indexierung übernommen!');
        }],
        'ondelete_callback' => [function (DataContainer $objDataContainer) {
            $objElasticsearch = new Elasticsearch((new Options())->getOptions());
            $objElasticsearch->deleteIndex($objDataContainer->id);
        }],
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => 2,
            'flag' => 12,
            'fields' => ['last_indexed'],
            'panelLayout' => 'filter,limit'
        ],
        'label' => [
            'fields' => ['last_indexed', 'title', 'url', 'state'],
            'showColumns' => true
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
            'reindex' => [
                'label' => &$GLOBALS['TL_LANG']['tl_indices']['reindex'],
                'href' => 'key=reindexIndex',
                'icon' => 'sync.svg'
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"'
            ]
        ]
    ],
    'palettes' => [
        'default' => '{settings_legend},settings,state;{types_legend},types;{page_legend},domain,url,language,pageId;{meta_legend},title,description,images'
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]
        ],
        'tstamp' => [
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'types' => [
            'inputType' => 'checkbox',
            'eval' => [
                'mandatory' => true,
                'multiple' => true,
                'tl_class' => 'clr'
            ],
            'options_callback' => function () {
                return (new Categories())->getCategories();
            },
            'filter' => true,
            'sql' => "blob NULL"
        ],
        'title' => [
            'inputType' => 'textarea',
            'eval' => [
                'mandatory' => true,
                'tl_class' => 'clr'
            ],
            'search' => true,
            'sql' => "text NULL"
        ],
        'description' => [
            'inputType' => 'textarea',
            'eval' => [
                'tl_class' => 'clr'
            ],
            'search' => true,
            'sql' => "text NULL"
        ],
        'url' => [
            'inputType' => 'text',
            'eval' => [
                'unique' => true,
                'doNotCopy' => true,
                'mandatory' => true,
                'tl_class' => 'w50'
            ],
            'search' => true,
            'sql' => "text NULL"
        ],
        'domain' => [
            'inputType' => 'text',
            'eval' => [
                'mandatory' => true,
                'tl_class' => 'w50'
            ],
            'filter' => true,
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'images' => [
            'inputType' => 'fileTree',
            'eval' => [
                'fieldType' => 'checkbox',
                'multiple' => true,
                'files' => true,
                'filesOnly' => true,
                'tl_class' => 'clr',
                'extensions' => ($GLOBALS['TL_CONFIG']['validImageTypes'] ?? '')
            ],
            'sql' => "blob NULL"
        ],
        'document' => [
            'sql' => "longblob NULL"
        ],
        'last_indexed' => [
            'flag' => 6,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'state' => [
            'inputType' => 'select',
            'eval' => [
                'mandatory' => true,
                'tl_class' => 'w50'
            ],
            'options_callback' => function () {
                return ($GLOBALS['TL_LANG']['tl_indices']['states'] ?? []);
            },
            'reference' => &$GLOBALS['TL_LANG']['tl_indices']['states'],
            'sql' => "varchar(16) NOT NULL default '" . States::ACTIVE . "'"
        ],
        'language' => [
            'inputType' => 'text',
            'eval' => [
                'tl_class' => 'w50',
                'mandatory' => true
            ],
            'sql' => "varchar(12) NOT NULL default ''"
        ],
        'doc_type' => [
            'filter' => true,
            'sql' => "varchar(32) NOT NULL default 'page'"
        ],
        'origin_url' => [
            'eval' => [
                'doNotCopy' => true
            ],
            'sql' => "text NULL"
        ],
        'pageId' => [
            'inputType' => 'pageTree',
            'eval' => [
                'dcaPicker' => true,
                'doNotCopy' => true,
                'decodeEntities' => true,
                'tl_class' => 'w50 wizard'
            ],
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true]
        ],
        'settings' => [
            'inputType' => 'checkbox',
            'eval' => [
                'multiple' => true,
                'tl_class' => 'clr',
                'submitOnChange' => true
            ],
            'save_callback' => [
                function ($strValue, DataContainer $objDataContainer) {
                    Input::post($objDataContainer->inputName, $strValue);
                    return $strValue;
                }
            ],
            'reference' => &$GLOBALS['TL_LANG']['tl_indices']['settings_options'],
            'options' => ['preventIndexMetadata', 'preventIndex', 'doNotShow'],
            'sql' => "blob NULL"
        ]
    ]
];