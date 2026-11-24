<?php

// Legends
$GLOBALS['TL_LANG']['tl_page']['ps_search_legend'] = 'ProSearch-Einstellungen';

// Fields
$GLOBALS['TL_LANG']['tl_page']['psAnalyzer'] = ['Analyzer', 'Zerlegt die Texte dieses Startpunkts in Suchbegriffe, z. B. „german“ (Stoppwörter, Wortstämme), „standard“ (Wörter ohne Satzzeichen) oder „contao“ (nur an Leerzeichen getrennt). Gilt für den Index und, passend dazu, für die Suchanfragen aller Suchmodule. Wirkt nach dem Neuaufbau des Index.'];
$GLOBALS['TL_LANG']['tl_page']['psAnalyzerRebuild'] = 'Der Analyzer wirkt erst nach dem Neuaufbau des Index: vendor/bin/contao-console prosearch:index:rebuild --root=%s --force. Bis dahin sucht die Suche weiter mit dem bisherigen Analyzer.';
$GLOBALS['TL_LANG']['tl_page']['psSearchCategory'] = ['Kategorie', 'Kategorien dieser Seite für die Suche, Komma getrennt (z. B. „page,leistung“). Leerzeichen, Binde- und Unterstriche sowie Punkte werden entfernt.'];
