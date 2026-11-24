<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\EventListener\DataContainer\IndicesListener;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SyncStatus;
use Contao\MemberGroupModel;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Input;

$GLOBALS['TL_DCA']['tl_indices'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'backendSearchIgnore' => true,
        'onload_callback' => [[IndicesListener::class, 'onLoad']],
        'onsubmit_callback' => [[IndicesListener::class, 'onSubmit']],
        'ondelete_callback' => [[IndicesListener::class, 'onDelete']],
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'url_hash' => 'unique',
                'sync_status,sync_next_at' => 'index',
                'sync_claim' => 'index'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => 2,
            'flag' => 12,
            'fields' => ['last_indexed'],
            'panelLayout' => 'filter,limit;sort,search'
        ],
        'label' => [
            'fields' => ['last_indexed', 'title', 'url', 'state', 'sync_status', 'chunk_count'],
            'showColumns' => true,
            // column "Suchindex": check mark, pending (gray) or failed (red), see IndicesListener::listLabel()
            'label_callback' => [IndicesListener::class, 'listLabel']
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
                'primary' => true,
                'icon' => 'edit.svg',
                'href' => 'act=edit'
            ],
            'reindex' => [
                'primary' => true,
                'label' => &$GLOBALS['TL_LANG']['tl_indices']['reindex'],
                'href' => 'key=reindexIndex',
                'icon' => 'sync.svg'
            ],
            'delete' => [
                'primary' => true,
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"'
            ],
            'show' => [
                'primary' => true,
                'href' => 'act=show',
                'icon' => 'show.svg',
            ]
        ]
    ],
    'palettes' => [
        '__selector__' => ['protected'],
        'default' => '{settings_legend},settings,state;{types_legend},types;{page_legend},domain,url,language,pageId;{meta_legend},title,description,images;{protected_legend},protected'
    ],
    'subpalettes' => [
        'protected' => 'groups'
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
                $options = [];
                $labels = (new Categories())->getTranslatedCategories();

                foreach ((new Categories())->getCategories() as $category) {
                    $label = $labels[$category]['label'] ?? $category;
                    $options[$category] = $label === $category ? $category : $label . ' (' . $category . ')';
                }

                return $options;
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
        'text' => [
            'search' => true,
            'sql' => "mediumtext NULL"
        ],
        'strong' => [
            'sql' => "blob NULL"
        ],
        'headings' => [
            'sql' => "blob NULL"
        ],
        'url_hash' => [
            'sql' => "char(40) NOT NULL default ''"
        ],
        'rootId' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'content_hash' => [
            'sql' => "char(64) NOT NULL default ''"
        ],
        'checksum' => [
            'sql' => "char(64) NOT NULL default ''"
        ],
        'source_hash' => [
            'sql' => "varchar(64) NOT NULL default ''"
        ],
        'chunk_count' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        // transfer queue (see Indexing\Queue\IndexQueue)
        'revision' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'synced_revision' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'sync_status' => [
            'filter' => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_indices']['sync_states'],
            'sql' => "varchar(16) NOT NULL default '" . SyncStatus::PENDING . "'"
        ],
        'sync_attempts' => [
            'sql' => ['type' => 'smallint', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'sync_error' => [
            'sql' => "text NULL"
        ],
        'sync_next_at' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'sync_claim' => [
            'sql' => "varchar(32) NOT NULL default ''"
        ],
        'sync_claimed_until' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'synced_index' => [
            'sql' => "varchar(255) NOT NULL default ''"
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
        ],
        'protected' => [
            'inputType' => 'checkbox',
            'eval' => [
                'tl_class' => 'clr',
                'submitOnChange' => true
            ],
            'filter' => true,
            'sql' => "char(1) NOT NULL default ''"
        ],
        'groups' => [
            'inputType' => 'checkbox',
            'eval' => [
                'multiple' => true,
                'tl_class' => 'clr'
            ],
            'options_callback' => function () {
                $arrMemberGroups = [];
                $objMemberGroups = MemberGroupModel::findAll();
                if (!$objMemberGroups) {
                    return [];
                }
                while ($objMemberGroups->next()) {
                    $arrMemberGroups[$objMemberGroups->id] = $objMemberGroups->name;
                }
                return $arrMemberGroups;
            },
            'filter' => true,
            'sql' => "blob NULL"
        ]
    ]
];