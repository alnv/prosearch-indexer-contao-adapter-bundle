# Contao Search Pro

### Professionelle Suche mit Elasticsearch für Contao 4.9, 4.13 und Contao 5

Mit stetig wachsenden Online-Inhalten wird es immer anspruchsvoller, relevanten und bedeutsamen Content schnell zu finden. Genau hier setzt Contao Search Pro an. Basierend auf der hochmodernen Elasticsearch-Technologie und ausgestattet mit einer Fülle an nützlichen Features.

- Definieren von eigenen Tags
- Filtern bereits in den Suchergebnissen
- Speichern von eingegebenen Suchbegriffen
- Statistiken
- Suchvorschläge
- Meinten Sie…?
- Bildvorschau
- Durchsuchen von PDF Dokumenten
- Synonyme

[![IMAGE ALT TEXT HERE](https://img.youtube.com/vi/MFjcACLUUbQ/0.jpg)](https://www.youtube.com/watch?v=MFjcACLUUbQ)

Mehr Details: https://www.sineos.de/contao/contao-search-pro

## Installation

Über Contao-Manager: https://extensions.contao.org/?q=pro&pages=1&p=alnv%2Fprosearch-indexer-contao-adapter-bundle

oder mit composer.json

``
composer require alnv/prosearch-indexer-contao-adapter-bundle
``

## Setup

Nach der Installation erscheint im Backend ein neuer Navigationspunkt namens "Elasticsearch". Als erstes musst du die Zugangsdaten eingeben. Klicke dazu auf "Zugangsdaten" und wähle unter "Paket" die Option "API-Key" aus. Trage anschließend deinen API-Token und den Lizenzschlüssel für deine Domain ein.

### Kostenlos testen

**Du kannst die Erweiterung jederzeit kostenlos testen, indem du dir einen Demo-Zugang erstellst: https://app.sineos.de/. Der Zugang ist 30 Tage gültig.**

### Seitenstruktur

In der Seitenstruktur im Seitentyp "Startpunkt einer Webseite" gibt es weitere Elasticsearch-Einstellungen. Hier kannst du für deine Webseite den passenden Analyzer auswählen.

### Was sind Analyzer?

In Elasticsearch ist ein Analyzer ein Feature, das für die Textanalyse verantwortlich ist. Das wird verwendet, um Textdaten in einem Dokument in sinnvolle Tokens (Wörter oder Begriffe) zu zerlegen und diese Tokens für die Indexierung und die spätere Suchabfrage zu optimieren. Elasticsearch unterstützt eine Vielzahl von Analyzern, die an die spezifischen Anforderungen Ihrer Anwendung angepasst werden können.

### Kategorien

In der Seitenstruktur im Seitentyp "Reguläre Seite" lassen sich die Kategorien eintragen. Diese können später für die Filterung der Suchergebnisse verwendet werden.

Es ist auch möglich, die Kategorien direkt im Template z.B. im News oder Event Modul zu vergeben.

``` php
<?php
// news_full.html5
$GLOBALS['TL_HEAD']['search:type'] = '<meta name="search:type" content="news"/>';
?>
```

Die Kategorien müssen kleingeschrieben und dürfen keine Sonderzeichen enthalten (einschließlich "-", "_").

Der search:type wird erst nach einem erneuten Aufbau des Suchindexes übernommen.

### Frontend-Modul

Es gibt zwei Frontend-Module "Elasticsearch" und "Elasticsearch Type Ahead". Lege dein gewünschtes Modul an und binde es in der Seite ein.

### Suchindex aufbauen

Jetzt kannst du den Suchindex aufbauen. Gehe dazu in die Systemwartung und klicke auf "Den Suchindex aktualisieren". Wenn die Seiten einmal indexiert sind, kannst du die Suche im Frontend aufrufen und testen.

### Kategorien Texte einpflegen

Nach der Indexierung stehen dir die Kategorien unter Elasticsearch → Kategorien zur Verfügung. Hier siehst du eine Auflistung aller gefundenen Kategorien. Zudem kannst du je Kategorie ein Label vergeben oder die Kategorie übersetzen.

### Synonyme

Es ist möglich, je Suchbegriff Synonyme anzulegen. Das kannst du unter Elasticsearch → Synonyme erledigen. Erstelle ein Synonym und trage beim "Suchbegriff" das wonach gesucht werden soll ein z.B. "Karriere". Und unter Synonyme andere Bezeichnungen für den Suchbegriff z.B. "Jobs", "Stellenbörse", "Arbeit" etc. Wenn Seitenbesucher einen der Synonyme in die Suche einträgt, dann wird nach "Karriere" gesucht.

### Statistik

Hier hast du einen Überblick über die gesuchten Suchbegriffe.

### Bilder anzeigen

Die Bilder für die Suche werden über die Open Graph Meta Tags gepflegt. Am besten installiert man eine Open Graph Erweiterung für Contao oder man vergibt je Seite ein og:image Tag. Der og:image wird erst nach einem erneuten Aufbau des Suchindexes übernommen.

``` html
<meta property=“og:image“ content="url"/>
```


## Entwickler:innen

### AJAX-Abfrage

Der folgende Code ist ein JavaScript-Skript, das eine Suchanfrage an einen Server sendet und die Ergebnisse im JSON-Format empfängt. Diese Ergebnisse können dann in einer eigenen Frontend-Anwendung verwendet werden. Der Code nutzt `XMLHttpRequest`, um eine POST-Anfrage zu senden und die Antwort zu verarbeiten.

```js
<script>
    (function () {
        // Definieren der Suchanfrage
        let strQuery = "Meine Anfrage"; // Das ist eure Suchabfrage
        
        // Definieren der Parameter für die Anfrage
        let strParams = "module=28&root=33"; 
        // Es müssen zwei Parameter übergeben werden:
        // 1. "module": Die ID des Frontend-Moduls
        // 2. "root": Die Root-ID in der Seitenstruktur
        
        // Erstellen eines neuen XMLHttpRequest-Objekts
        let objXHttp = new XMLHttpRequest();
        
        // Festlegen einer Funktion, die aufgerufen wird, wenn sich der Zustand des XMLHttpRequest-Objekts ändert
        objXHttp.onreadystatechange = function() {
            // Überprüfen, ob die Anfrage abgeschlossen ist (readyState === 4) und ob sie erfolgreich war (status === 200)
            if (this.readyState === 4 && this.status === 200) {
                // Parsen der JSON-Antwort
                let json = JSON.parse(this.responseText);
                // Hier könnt ihr mit den Daten weiterarbeiten ;)
                console.log(json); // Beispiel: Ausgabe der Daten in der Konsole
            }
        };
        
        // Öffnen der POST-Anfrage mit der URL und den Query-Parametern
        objXHttp.open("POST", "/elastic/search/results?query=" + encodeURIComponent(strQuery), true);
        
        // Setzen des Content-Type-Headers für die Anfrage
        objXHttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        
        // Senden der Anfrage mit den Parametern
        objXHttp.send(strParams);
    })();
</script>
```

1. **Definieren der Suchanfrage und Parameter**:

    ```js
    let strQuery = "Meine Anfrage";
    let strParams = "module=28&root=33";
    ```
   
   Hier werden die Suchanfrage und die benötigten Parameter (`module` und `root`) festgelegt.


2. **Erstellen des XMLHttpRequest-Objekts**:

    ```js
    let objXHttp = new XMLHttpRequest();
    ```


3. **Festlegen der Callback-Funktion**:

    ```js
    objXHttp.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
            let json = JSON.parse(this.responseText);
            console.log(json);
        }
    };
    ```
   
   Diese Funktion wird jedes Mal aufgerufen, wenn sich der Zustand des XMLHttpRequest-Objekts ändert. Wenn die Anfrage abgeschlossen ist und erfolgreich war, wird die JSON-Antwort geparst und in der Konsole ausgegeben.


4. **Öffnen der POST-Anfrage**:

    ```js
    objXHttp.open("POST", "/elastic/search/results?query=" + encodeURIComponent(strQuery), true);
    ```
   
   Die Anfrage wird geöffnet, wobei die URL die Suchanfrage als Query-Parameter enthält.


5. **Setzen des Content-Type-Headers**:

    ```js
    objXHttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    ```
   Der Content-Type-Header wird auf `application/x-www-form-urlencoded` gesetzt.


6. **Senden der Anfrage**:

    ```js
    objXHttp.send(strParams);
    ```
   
   Die Anfrage wird mit den Parametern gesendet.

Mit diesem Skript könnt ihr eine Suchanfrage an den Server senden und die Ergebnisse im JSON-Format laden, um sie in eurer eigenen Frontend-Anwendung zu verwenden.