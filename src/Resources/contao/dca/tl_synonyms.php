<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Synonyms;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Message;
use Contao\StringUtil;

$GLOBALS['TL_DCA']['tl_synonyms'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'backendSearchIgnore' => true,
        'onsubmit_callback' => [static function (DataContainer $dc): void {
            Synonyms::reset();

            if (!$dc->activeRecord || '1' === (string)$dc->activeRecord->disable) {
                return;
            }

            $own = array_map([Synonyms::class, 'normalize'], StringUtil::deserialize($dc->activeRecord->synonyms, true));
            $others = (new Synonyms(\Contao\Database::getInstance()->prepare("SELECT keyword, synonyms FROM tl_synonyms WHERE disable!='1' AND id!=?")->execute($dc->id)->fetchAllAssoc()))->getSynonyms();
            $shared = [];

            foreach ($own as $term) {
                if ('' !== $term && isset($others[$term])) {
                    $shared[] = $term . ' → ' . implode(', ', $others[$term]);
                }
            }

            if ([] !== $shared) {
                Message::addInfo(sprintf($GLOBALS['TL_LANG']['tl_synonyms']['sharedTerms'] ?? 'Also used in other records: %s', implode('; ', $shared)));
            }
        }],
        'ondelete_callback' => [static fn() => Synonyms::reset()],
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
            'panelLayout' => 'filter;search,limit'
        ],
        'label' => [
            'fields' => ['keyword', 'synonyms'],
            'label_callback' => static function (array $row): string {
                $terms = array_filter(array_map(static fn($t): string => StringUtil::decodeEntities((string)$t), StringUtil::deserialize($row['synonyms'] ?? null, true)));
                $label = StringUtil::specialchars(StringUtil::decodeEntities((string)$row['keyword']))
                    . ' <span class="label-info">← ' . StringUtil::specialchars(mb_strimwidth(implode(', ', $terms), 0, 120, '…')) . '</span>';

                if ('1' === (string)($row['disable'] ?? '')) {
                    $label = '<span class="tl_gray">' . $label . ' [' . ($GLOBALS['TL_LANG']['tl_synonyms']['disabledLabel'] ?? 'disabled') . ']</span>';
                }

                return $label;
            }
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
            'search' => true,
            'save_callback' => [static function ($value, DataContainer $dc) {
                $keyword = Synonyms::normalize((string)($dc->activeRecord->keyword ?? ''));
                $postedKeyword = Synonyms::normalize((string)\Contao\Input::post('keyword'));
                $seen = [];
                $terms = [];

                foreach (StringUtil::deserialize($value, true) as $term) {
                    $term = trim((string)preg_replace('/\s+/u', ' ', (string)$term));
                    $key = Synonyms::normalize($term);

                    if ('' === $key || isset($seen[$key]) || $key === $keyword || $key === $postedKeyword) {
                        continue;
                    }

                    $seen[$key] = true;
                    $terms[] = $term;
                }

                if ([] === $terms) {
                    throw new \RuntimeException($GLOBALS['TL_LANG']['tl_synonyms']['noTerms'] ?? 'Please enter at least one search term that differs from the target term.');
                }

                return serialize($terms);
            }],
            'sql' => "text NULL"
        ],
        'disable' => [
            'inputType' => 'checkbox',
            'filter' => true,
            'eval' => [
                'tl_class' => 'clr'
            ],
            'sql' => "char(1) NOT NULL default ''"
        ]
    ]
];