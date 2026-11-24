<?php

// Fields
$GLOBALS['TL_LANG']['tl_search_credentials']['id'] = ['ID', 'Eindeutige ID des Datensatzes.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['tstamp'] = ['Änderungsdatum', 'Zeitpunkt der letzten Änderung.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['signature'] = ['Signatur', 'Kennung dieser Installation. Sie ist Teil der Indexnamen und wird automatisch erzeugt.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['type'] = ['Paket', 'Art der Anbindung.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['licence'] = ['API-Key', 'Anbindung über den ProSearch-Proxy mit Lizenzschlüssel.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['proxyUrl'] = ['ProSearch-Proxy-URL', 'Basis-URL des ProSearch-Proxys ohne /api/v1, z. B. https://proxy.example.org. Leer lassen, um den konfigurierten Standard (PROSEARCH_PROXY_URL) zu verwenden.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['proxyApiKey'] = ['Proxy-API-Key', 'Optional: API-Key, falls der Proxy einen vergibt (Header X-Api-Key). Leer = PROSEARCH_PROXY_API_KEY.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['authToken'] = ['API-Token', 'Zugangstoken zum Lizenzschlüssel. Er wird zusammen mit Schlüssel und Domain an den Proxy übertragen.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['keys'] = ['API-Schlüssel', 'Lizenzschlüssel je Domain. Ohne passende Domain wird der erste Schlüssel verwendet.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['key'] = ['API-Schlüssel', 'Lizenzschlüssel.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['domain'] = ['Domain', 'Domain, für die der Schlüssel gilt (ohne Protokoll, z. B. www.example.org).'];
$GLOBALS['TL_LANG']['tl_search_credentials']['singleDocument'] = ['Alle Dokumente in einer einzigen Datenbank sammeln', 'Alle deine Seiten (Domains) landen in derselben Datenbank (auch externe Seiten), dadurch ist es möglich alle Seiten unabhängig von der Domain durchzusuchen.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['analyzer'] = ['Analyzer', 'Analyzer der gemeinsamen Datenbank (nur mit „Alle Dokumente in einer einzigen Datenbank sammeln“).'];

// Search engine detection (read-only, set automatically)
$GLOBALS['TL_LANG']['tl_search_credentials']['engineType'] = ['Suchserver', 'Automatisch erkannt (OpenSearch/Elasticsearch).'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineVersion'] = ['Version', 'Version des Suchservers.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineChunks'] = ['Chunks', 'Chunks werden übertragen (OpenSearch 3 oder neuer).'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineVectors'] = ['Vektorsuche', 'k-NN-Plugin vorhanden (für spätere Embeddings).'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineCheckedAt'] = ['Erkannt am', 'Zeitpunkt der letzten Erkennung.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineError'] = ['Fehler der Erkennung', 'Fehlermeldung, falls der Suchserver nicht erkannt werden konnte.'];
$GLOBALS['TL_LANG']['tl_search_credentials']['engineConnection'] = ['Erkannte Verbindung', 'Proxy-Verbindung, für die das Ergebnis gilt. Ändert sie sich, wird neu erkannt.'];

// Legacy fields (ProSearch < 4, direct connection to the cluster)
$GLOBALS['TL_LANG']['tl_search_credentials']['host'] = ['Host', ''];
$GLOBALS['TL_LANG']['tl_search_credentials']['port'] = ['Port', ''];
$GLOBALS['TL_LANG']['tl_search_credentials']['username'] = ['Benutzer', ''];
$GLOBALS['TL_LANG']['tl_search_credentials']['password'] = ['Passwort', ''];
$GLOBALS['TL_LANG']['tl_search_credentials']['cloudid'] = ['Cloud-ID', ''];
$GLOBALS['TL_LANG']['tl_search_credentials']['cert'] = ['Cert-Path', ''];

// Operations
$GLOBALS['TL_LANG']['tl_search_credentials']['edit'] = ['Bearbeiten', 'Datensatz ID %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_search_credentials']['delete'] = ['Löschen', 'Datensatz ID %s löschen'];
$GLOBALS['TL_LANG']['tl_search_credentials']['show'] = ['Details', 'Details des Datensatzes ID %s anzeigen'];
