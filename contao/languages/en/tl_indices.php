<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;

$GLOBALS['TL_LANG']['tl_indices']['types_legend'] = 'Types';
$GLOBALS['TL_LANG']['tl_indices']['meta_legend'] = 'Meta';
$GLOBALS['TL_LANG']['tl_indices']['page_legend'] = 'Seite';
$GLOBALS['TL_LANG']['tl_indices']['settings_legend'] = 'Einstellungen';
$GLOBALS['TL_LANG']['tl_indices']['document_legend'] = 'Index';

$GLOBALS['TL_LANG']['tl_indices']['types'] = ['Types', ''];
$GLOBALS['TL_LANG']['tl_indices']['title'] = ['Titel', 'Anzeige in dem Suchergebnis.'];
$GLOBALS['TL_LANG']['tl_indices']['description'] = ['Beschreibung', 'Anzeige in dem Suchergebnis.'];
$GLOBALS['TL_LANG']['tl_indices']['url'] = ['URL', 'Indexierte Seite'];
$GLOBALS['TL_LANG']['tl_indices']['domain'] = ['Domain', ''];
$GLOBALS['TL_LANG']['tl_indices']['images'] = ['Vorschaubilder', ''];
$GLOBALS['TL_LANG']['tl_indices']['document'] = ['Daten', ''];
$GLOBALS['TL_LANG']['tl_indices']['last_indexed'] = ['Zuletzt indexiert am', ''];
$GLOBALS['TL_LANG']['tl_indices']['state'] = ['Status', ''];
$GLOBALS['TL_LANG']['tl_indices']['language'] = ['Sprache', ''];
$GLOBALS['TL_LANG']['tl_indices']['doc_type'] = ['Dokumenten Typ', ''];
$GLOBALS['TL_LANG']['tl_indices']['origin_url'] = ['Ursprünglich URL', ''];
$GLOBALS['TL_LANG']['tl_indices']['pageId'] = ['Seite', ''];
$GLOBALS['TL_LANG']['tl_indices']['vector_files'] = ['Vektordatei erstellen', ''];
$GLOBALS['TL_LANG']['tl_indices']['reindex'] = ['Seite Re-indexieren', ''];
$GLOBALS['TL_LANG']['tl_indices']['settings'] = ['Sucheinstellungen', 'Hier kannst du das Verhalten bei der Indexierung des Dokumentes ändern.'];

$GLOBALS['TL_LANG']['tl_indices']['indices_text'] = ['Text', ''];
$GLOBALS['TL_LANG']['tl_indices']['indices_strong'] = ['Strong tags', ''];
$GLOBALS['TL_LANG']['tl_indices']['indices_h1'] = ['H1 tags', ''];
$GLOBALS['TL_LANG']['tl_indices']['indices_h2'] = ['H2 tags', ''];
$GLOBALS['TL_LANG']['tl_indices']['indices_h3'] = ['H3 tags', ''];
$GLOBALS['TL_LANG']['tl_indices']['indices_h4'] = ['H4 tags', ''];
$GLOBALS['TL_LANG']['tl_indices']['indices_h5'] = ['H5 tags', ''];
$GLOBALS['TL_LANG']['tl_indices']['indices_h6'] = ['H6 tags', ''];
$GLOBALS['TL_LANG']['tl_indices']['indices_document'] = ['PDF text', ''];

$GLOBALS['TL_LANG']['tl_indices']['settings_options'] = [
    'preventIndexMetadata' => 'Titel, Beschreibung und Bild nicht aktualisieren',
    'preventIndex' => 'Re-Indexierung verhindern',
    'doNotShow' => 'In der Suche verbergen'
];

$GLOBALS['TL_LANG']['tl_indices']['states'][States::ACTIVE] = 'Aktiv';
$GLOBALS['TL_LANG']['tl_indices']['states'][States::DELETE] = 'Gelöscht';