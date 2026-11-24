<?php

use Contao\DC_Table;

/**
 * Text parts (chunks) of the documents in tl_indices, written by the crawler/queue only.
 *
 * At most "indexing.chunk_size" tokens per chunk (default 600), consecutive chunks overlap by
 * "indexing.chunk_overlap" tokens (default 60). "hash" identifies unchanged chunks, e.g. to reuse
 * embeddings later.
 */
$GLOBALS['TL_DCA']['tl_indices_chunks'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_indices',
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'notDeletable' => true,
        'backendSearchIgnore' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid,sorting' => 'unique'
            ]
        ]
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]
        ],
        'pid' => [
            'foreignKey' => 'tl_indices.title',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'tstamp' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'sorting' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'text' => [
            'sql' => "mediumtext NULL"
        ],
        'token_count' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'start_offset' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'end_offset' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'hash' => [
            'sql' => "char(64) NOT NULL default ''"
        ]
    ]
];
