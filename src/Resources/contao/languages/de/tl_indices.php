<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;

// Legends
$GLOBALS['TL_LANG']['tl_indices']['settings_legend'] = 'Einstellungen';
$GLOBALS['TL_LANG']['tl_indices']['types_legend'] = 'Kategorien';
$GLOBALS['TL_LANG']['tl_indices']['page_legend'] = 'Seite';
$GLOBALS['TL_LANG']['tl_indices']['meta_legend'] = 'Metadaten';
$GLOBALS['TL_LANG']['tl_indices']['document_legend'] = 'Indexierter Inhalt';
$GLOBALS['TL_LANG']['tl_indices']['protected_legend'] = 'Zugriffsschutz';

// Fields
$GLOBALS['TL_LANG']['tl_indices']['id'] = ['ID', 'Eindeutige ID des Datensatzes. Sie ist zugleich die Dokument-ID im Suchindex.'];
$GLOBALS['TL_LANG']['tl_indices']['tstamp'] = ['Änderungsdatum', 'Zeitpunkt der letzten Änderung des Datensatzes.'];
$GLOBALS['TL_LANG']['tl_indices']['settings'] = ['Sucheinstellungen', 'Legt fest, wie das Dokument bei der nächsten Indexierung behandelt wird.'];
$GLOBALS['TL_LANG']['tl_indices']['state'] = ['Status', '„Aktiv“: Das Dokument ist durchsuchbar. „Gelöscht“: Es wird beim nächsten Lauf der Übertragung aus dem Suchindex entfernt.'];
$GLOBALS['TL_LANG']['tl_indices']['types'] = ['Kategorien', 'Kategorien des Dokuments. Nach ihnen filtert die Suche (Modul-Einstellung „Kategorien“), sie gruppieren die Ergebnisse.'];
$GLOBALS['TL_LANG']['tl_indices']['domain'] = ['Domain', 'Domain, unter der das Dokument erreichbar ist. Sie bestimmt den Lizenzschlüssel.'];
$GLOBALS['TL_LANG']['tl_indices']['url'] = ['URL', 'Adresse des indexierten Dokuments. Jede URL kommt nur einmal vor.'];
$GLOBALS['TL_LANG']['tl_indices']['url_hash'] = ['URL-Hash', 'SHA-1 der URL (eindeutiger Schlüssel, wird automatisch gesetzt).'];
$GLOBALS['TL_LANG']['tl_indices']['origin_url'] = ['Ursprüngliche URL', 'Adresse, unter der das Dokument gefunden wurde, z. B. vor einer Weiterleitung.'];
$GLOBALS['TL_LANG']['tl_indices']['language'] = ['Sprache', 'Sprache des Dokuments (z. B. „de“). Die Suche liefert nur Dokumente der Sprache des Suchmoduls bzw. der Seite.'];
$GLOBALS['TL_LANG']['tl_indices']['pageId'] = ['Seite', 'Contao-Seite, auf der das Dokument indexiert wurde.'];
$GLOBALS['TL_LANG']['tl_indices']['rootId'] = ['Startpunkt', 'ID der Root-Seite. Sie bestimmt den Suchindex, in dem das Dokument liegt.'];
$GLOBALS['TL_LANG']['tl_indices']['doc_type'] = ['Dokumenttyp', '„page“ für Seiten, „file“ bzw. der Dateityp für Dokumente wie PDFs.'];
$GLOBALS['TL_LANG']['tl_indices']['title'] = ['Titel', 'Überschrift des Suchtreffers. Nur bearbeitbar, wenn „Titel, Beschreibung und Bild nicht aktualisieren“ oder „Re-Indexierung verhindern“ gesetzt ist.'];
$GLOBALS['TL_LANG']['tl_indices']['description'] = ['Beschreibung', 'Teaser des Suchtreffers. Nur bearbeitbar, wenn „Titel, Beschreibung und Bild nicht aktualisieren“ oder „Re-Indexierung verhindern“ gesetzt ist.'];
$GLOBALS['TL_LANG']['tl_indices']['images'] = ['Vorschaubilder', 'Bilder, die im Suchtreffer angezeigt werden.'];
$GLOBALS['TL_LANG']['tl_indices']['text'] = ['Text', 'Vollständiger Text des Dokuments.'];
$GLOBALS['TL_LANG']['tl_indices']['strong'] = ['Hervorgehobene Begriffe', 'Fett gedruckte Begriffe (<strong>/<b>). Sie werden in der Suche höher gewichtet.'];
$GLOBALS['TL_LANG']['tl_indices']['headings'] = ['Überschriften', 'Überschriften h1–h6 des Dokuments. Sie werden in der Suche höher gewichtet.'];
$GLOBALS['TL_LANG']['tl_indices']['protected'] = ['Index schützen', 'Das Dokument nur angemeldeten Mitgliedern der ausgewählten Gruppen anzeigen.'];
$GLOBALS['TL_LANG']['tl_indices']['groups'] = ['Mitgliedergruppen', 'Nur Mitglieder dieser Gruppen finden das Dokument.'];
$GLOBALS['TL_LANG']['tl_indices']['last_indexed'] = ['Zuletzt indexiert am', 'Zeitpunkt, zu dem das Dokument zuletzt erfasst wurde.'];
$GLOBALS['TL_LANG']['tl_indices']['checksum'] = ['Prüfsumme', 'Prüfsumme aus Metadaten und Inhalt. Ändert sie sich nicht, wird das Dokument nicht erneut übertragen.'];
$GLOBALS['TL_LANG']['tl_indices']['content_hash'] = ['Inhalts-Hash', 'SHA-256 des Textes. Ändert er sich, werden die Chunks neu berechnet.'];
$GLOBALS['TL_LANG']['tl_indices']['source_hash'] = ['Quell-Hash', 'Hash der Quelldatei (z. B. PDF). Ist die Datei unverändert, wird sie nicht erneut ausgelesen.'];
$GLOBALS['TL_LANG']['tl_indices']['chunk_count'] = ['Chunks', 'Anzahl der Textabschnitte (max. 600 Tokens) für die semantische Suche.'];
$GLOBALS['TL_LANG']['tl_indices']['revision'] = ['Revision', 'Wird bei jeder inhaltlichen Änderung erhöht.'];
$GLOBALS['TL_LANG']['tl_indices']['synced_revision'] = ['Übertragene Revision', 'Revision, die zuletzt in den Suchindex übertragen wurde.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_status'] = ['Suchindex', 'Stand im Suchindex: ✓ = aktuell übertragen, „ausstehend“ = wird beim nächsten Lauf übertragen (in der Regel innerhalb einer Minute), „fehlgeschlagen“ = siehe „Letzter Übertragungsfehler“.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_attempts'] = ['Übertragungsversuche', 'Anzahl der fehlgeschlagenen Versuche seit der letzten erfolgreichen Übertragung.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_error'] = ['Letzter Übertragungsfehler', 'Fehlermeldung des letzten fehlgeschlagenen Versuchs.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_next_at'] = ['Nächster Versuch', 'Frühester Zeitpunkt der nächsten Übertragung.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_claim'] = ['Übertragungs-Token', 'Kennung des Laufs, der den Datensatz gerade überträgt.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_claimed_until'] = ['Reserviert bis', 'Bis zu diesem Zeitpunkt ist der Datensatz für den laufenden Übertragungslauf reserviert.'];
$GLOBALS['TL_LANG']['tl_indices']['synced_index'] = ['Suchindex', 'Name des Index, in den das Dokument zuletzt übertragen wurde.'];

// Virtual fields (indexed content, see IndicesListener)
$GLOBALS['TL_LANG']['tl_indices']['indices_text'] = ['Text', 'Vollständiger Text (Seite bzw. PDF). Nur mit „Re-Indexierung verhindern“ bearbeitbar.'];
$GLOBALS['TL_LANG']['tl_indices']['indices_strong'] = ['Hervorgehobene Begriffe', 'Fett gedruckte Begriffe. Nur mit „Re-Indexierung verhindern“ bearbeitbar.'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h1'] = ['Überschriften H1', 'Nur mit „Re-Indexierung verhindern“ bearbeitbar.'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h2'] = ['Überschriften H2', 'Nur mit „Re-Indexierung verhindern“ bearbeitbar.'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h3'] = ['Überschriften H3', 'Nur mit „Re-Indexierung verhindern“ bearbeitbar.'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h4'] = ['Überschriften H4', 'Nur mit „Re-Indexierung verhindern“ bearbeitbar.'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h5'] = ['Überschriften H5', 'Nur mit „Re-Indexierung verhindern“ bearbeitbar.'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h6'] = ['Überschriften H6', 'Nur mit „Re-Indexierung verhindern“ bearbeitbar.'];
$GLOBALS['TL_LANG']['tl_indices']['indices_document'] = ['PDF-Text', 'Text des PDF-Dokuments.'];

// Operations
$GLOBALS['TL_LANG']['tl_indices']['edit'] = ['Bearbeiten', 'Datensatz ID %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_indices']['delete'] = ['Löschen', 'Datensatz ID %s löschen'];
$GLOBALS['TL_LANG']['tl_indices']['show'] = ['Details', 'Details des Datensatzes ID %s anzeigen'];
$GLOBALS['TL_LANG']['tl_indices']['reindex'] = ['Neu indexieren', 'Seite ID %s neu indexieren'];

// References
$GLOBALS['TL_LANG']['tl_indices']['settings_options'] = [
    'preventIndexMetadata' => 'Titel, Beschreibung und Bild nicht aktualisieren',
    'preventIndex' => 'Re-Indexierung verhindern (Inhalt manuell pflegen)',
    'doNotShow' => 'In der Suche verbergen'
];
$GLOBALS['TL_LANG']['tl_indices']['states'][States::ACTIVE] = 'Aktiv';
$GLOBALS['TL_LANG']['tl_indices']['states'][States::DELETE] = 'Gelöscht';
$GLOBALS['TL_LANG']['tl_indices']['sync_states'] = [
    'pending' => 'ausstehend',
    'done' => 'übertragen',
    'failed' => 'fehlgeschlagen'
];
