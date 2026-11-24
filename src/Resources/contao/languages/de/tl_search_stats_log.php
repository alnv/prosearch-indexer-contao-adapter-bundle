<?php

// Fields
$GLOBALS['TL_LANG']['tl_search_stats_log']['id'] = ['ID', 'Eindeutige ID des Eintrags.'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['tstamp'] = ['Datum', 'Zeitpunkt der Suche. Bei übernommenen Altdaten das Datum der letzten Suche nach diesem Begriff.'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['keyword'] = ['Suchbegriff', 'Eingegebener Suchbegriff.'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['hits'] = ['Treffer', 'Anzahl der Suchergebnisse'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['clicks'] = ['Klicks', 'Klicks auf Suchergebnisse (Konvertierungen)'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['urls'] = ['Aufgerufene Seiten', 'Suchergebnisse, die nach dieser Suche angeklickt wurden.'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['types'] = ['Kontext', 'Kategorien der Suche'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['source'] = ['Quelle', 'Seite, auf der gesucht wurde'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['legacy'] = ['Altdaten', 'Aus der früheren Statistik (tl_search_stats) übernommen'];

// Operations
$GLOBALS['TL_LANG']['tl_search_stats_log']['delete'] = ['Löschen', 'Eintrag ID %s löschen'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['show'] = ['Details', 'Details des Eintrags ID %s anzeigen'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['export_terms'] = ['Begriffe exportieren', 'Suchbegriffe mit Anzahl, Treffern und Klicks als CSV exportieren'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['export_log'] = ['Protokoll exportieren', 'Alle Einträge des Suchprotokolls als CSV exportieren'];
$GLOBALS['TL_LANG']['tl_search_stats_log']['clear_log'] = ['Protokoll leeren', 'Alle Einträge des Suchprotokolls löschen'];

// Messages
$GLOBALS['TL_LANG']['tl_search_stats_log']['clearConfirm'] = 'Wollen Sie das komplette Suchprotokoll inklusive Altdaten löschen? Das kann nicht rückgängig gemacht werden.';
$GLOBALS['TL_LANG']['tl_search_stats_log']['cleared'] = 'Das Suchprotokoll wurde geleert.';

// CSV export columns
$GLOBALS['TL_LANG']['tl_search_stats_log']['csv'] = [
    'date' => 'Datum',
    'keyword' => 'Suchbegriff',
    'hits' => 'Treffer',
    'clicks' => 'Klicks',
    'urls' => 'Aufgerufene Seiten',
    'types' => 'Kontext',
    'source' => 'Quelle',
    'legacy' => 'Altdaten',
    'searches' => 'Suchanfragen',
    'avg_hits' => 'Ø Treffer',
    'zero' => 'Suchen ohne Treffer',
    'first' => 'Erste Suche',
    'last' => 'Letzte Suche'
];
