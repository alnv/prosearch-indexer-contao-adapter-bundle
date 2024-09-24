<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Entity\SearchVectorFile;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Categories;
use Contao\Database;
use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_search_vector_files'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'onsubmit_callback' => [function (DataContainer $objDataContainer) {
            $objSearchVectorFile = new SearchVectorFile($objDataContainer->id);
            $strUuid = $objSearchVectorFile->save('files/_vectors');

            Database::getInstance()
                ->prepare('UPDATE tl_search_vector_files %s WHERE id=?')
                ->set(['file' => $strUuid])
                ->limit(1)
                ->execute($objDataContainer->id);
        }],
        'onload_callback' => [function (DataContainer $objDataContainer) {
            if (!Input::get('vector_files')) {
                return;
            }

            if (Input::get('vector_files') == 'update') {
                $objSearchVectorFile = new SearchVectorFile($objDataContainer->id);
                $objSearchVectorFile->update();
            }

            Controller::redirect(preg_replace('/&(amp;)?vector_files=[^&]*/i', '', preg_replace('/&(amp;)?' . preg_quote(Input::get('vector_files'), '/') . '=[^&]*/i', '', Environment::get('request'))));
        }],
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['name'],
            'panelLayout' => 'filter,search;sort,limit'
        ],
        'label' => [
            'fields' => ['name', 'file'],
            'showColumns' => true
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg'
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"'
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg'
            ],
            'update' => [
                'icon' => 'sync.svg',
                'href' => 'vector_files=update',
                'attributes' => 'onclick="if(!confirm(\'Soll der Vector Store aktualisiert werden?\'))return false;Backend.getScrollOffset()"'
            ]
        ]
    ],
    'palettes' => [
        'default' => 'name;fields;types'
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]
        ],
        'tstamp' => [
            'flag' => 6,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'name' => [
            'inputType' => 'text',
            'eval' => [
                'mandatory' => true,
                'maxlength' => 128,
                'tl_class' => 'w50',
                'unique' => true,
                'decodeEntities' => true
            ],
            'search' => true,
            'sql' => ['type' => 'string', 'length' => 128, 'default' => '']
        ],
        'fields' => [
            'inputType' => 'checkboxWizard',
            'eval' => [
                'mandatory' => true,
                'multiple' => true,
                'tl_class' => 'clr'
            ],
            'options_callback' => function () {
                return ['description', 'text', 'strong', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'document', 'microdata'];
            },
            'filter' => true,
            'sql' => "blob NULL"
        ],
        'types' => [
            'inputType' => 'checkboxWizard',
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
        'file' => [
            'sql' => "blob NULL"
        ]
    ]
];