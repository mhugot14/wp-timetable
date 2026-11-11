# Timetable Schulverwaltung

WordPress-Plugin zur Planung von Zeugnis- und Prüfungsterminen (Noteneingabe,
Zeugniskonferenzen, Zeugnisausgabe, APA …) je Bildungsgang. Die Termine werden
im Frontend als GANTT-Diagramm, Liste oder Monatskalender angezeigt; pro
Bildungsgang gibt es einen iCal-Export.

Bei Fragen: hugot@lebk-muenster.de

## Voraussetzungen

- WordPress 6.x
- PHP 7.4 oder neuer

## Installation

ZIP über *Plugins → Installieren → Plugin hochladen* einspielen und aktivieren.
Die Datenbanktabellen werden beim Aktivieren bzw. nach einem Update automatisch
angelegt und migriert. Der Ordner `vendor/` wird nicht benötigt.

## Bedienung

| Menüpunkt | Inhalt |
|---|---|
| **Timetables** | Zeittafeln anlegen, bearbeiten, löschen und mit Datumsverschiebung kopieren |
| **Termine** | Termine anlegen/bearbeiten; Mehrfachänderung, Kopieren und Löschen über die Sammelaktionen; Filter nach Timetable, Bildungsgang und Ereignistyp |
| **Bildungsgänge** | Kürzel, Bezeichnung, Abteilungsleitung, Bildungsgangassistenzen, aktiv/inaktiv; Dubletten zusammenführen |
| **Ereignistypen** | Kürzel, Bezeichnung, Farbe, aktiv/inaktiv; Dubletten zusammenführen |
| **Einstellungen** | Ferien und Feiertage (manuell oder per API), abhängige Fristen |

## Rollen und Rechte

| Rolle | Darf |
|---|---|
| **Admin** (`manage_options`) | alles, im Backend und im Frontend |
| **Abteilungsleitung (AL)** | Termine *ihrer* Bildungsgänge im Frontend anlegen, ändern, löschen |
| **Bildungsgangassistenz (BGA)** | wie AL, für die Bildungsgänge, denen sie zugeordnet ist |

AL und BGA werden unter *Bildungsgänge* je Bildungsgang zugeordnet (eine AL,
beliebig viele BGA). Eine Person kann in mehreren Bildungsgängen AL oder BGA sein.
Bildungsgänge, Ereignistypen, Timetables, Status, Kopieren, Ferien und Fristen
bleiben den Admins vorbehalten.

Jede Änderung wird auf dem Server geprüft. Termine können nicht in fremde
Bildungsgänge oder (außer durch Admins) in eine andere Timetable verschoben
werden. Mitverschiebungen durch Fristen bleiben immer im selben Bildungsgang.

## Bearbeiten im Frontend

Wer Bearbeitungsrechte hat, sieht über dem Plan den Knopf **Bearbeiten**. Im
Bearbeiten-Modus sind die eigenen Termine gestrichelt umrandet:

- Termin anklicken (GANTT, Liste oder Monat) → Dialog zum Ändern oder Löschen
- Termin im Zeitplan oder GANTT seitlich ziehen → verschiebt ihn tageweise (Speichern beim Loslassen)
- „+“ neben einem Bildungsgang oder „+ Neuer Termin“ → Termin anlegen
- Nach dem Speichern zeigt der Dialog mitverschobene Termine (Fristen) und
  Hinweise (Wochenende, Ferien, Überschneidungen) an, danach lädt die Seite neu.

Der Modus bleibt beim Neuladen erhalten (pro Browser-Tab).

### REST-Schnittstelle

`/wp-json/mh-timetable/v1/` (Anmeldung per WordPress-Cookie + `X-WP-Nonce`):

| Methode | Pfad | Zweck |
|---|---|---|
| GET | `/ich` | eigene Rechte (Admin? Welche Bildungsgänge?) |
| GET | `/termine/{id}` | Termin lesen (nur mit Bearbeitungsrecht) |
| POST | `/termine` | Termin anlegen |
| PUT | `/termine/{id}` | Termin ändern (nicht übergebene Felder bleiben) |
| DELETE | `/termine/{id}` | Termin löschen |

Antworten beim Speichern enthalten `termin` und `hinweise` (`info` =
Mitverschiebungen, `warning` = Konflikte). Fehler: 400 (ungültig, mit
`data.details`), 401 (nicht angemeldet), 403 (keine Berechtigung), 404.

## Shortcode

```
[insertTimetable id="12"]
[insertTimetable id="12" entwurf="ja"]
```

| Attribut | Bedeutung |
|---|---|
| `id` | ID der Timetable (den fertigen Shortcode zeigt die Spalte *Shortcode* unter *Timetables*) |
| `entwurf` | veraltet – besser den **Status** der Timetable auf *Entwurf* setzen. `ja` wirkt weiterhin wie der Status *Entwurf*. |
| `ansichten` | optional: wählbare Ansichten für diese Seite, z. B. `zeitplan,liste` (überschreibt die Einstellung) |
| `standard` | optional: Ansicht beim Öffnen, z. B. `liste` |

## Ansichten im Frontend

| Ansicht | Inhalt |
|---|---|
| **Zeitplan** | Neu gedachte Zeitleiste: ausgeschriebene Ereignistypen am Balken, Stichtage als Raute, Zeiträume als Balken, Monat/Kalenderwoche/Tag, Ferienbänder, Heute-Linie (springt beim Öffnen zu heute), Langname des Bildungsgangs |
| **GANTT** | Klassisches Tagesraster mit Kürzeln |
| **Liste** | Chronologische Karten – gut auf dem Handy |
| **Monat** | Kalenderblätter je Monat |

Unter *Einstellungen → Ansichten im Frontend* wird festgelegt, welche Ansichten
wählbar sind und welche beim Öffnen erscheint (Standard: alle, Zeitplan zuerst).
Ist nur eine Ansicht aktiv, entfallen die Umschalt-Knöpfe.

## Status: Entwurf / Veröffentlicht

Jede Timetable hat einen Status (Spalte *Status*, änderbar im Bearbeiten-Dialog).

- **Entwurf:** Das Frontend zeigt den Hinweis „Entwurf – noch nicht verbindlich“.
  Es gibt keine iCal-Links, und der iCal-Endpunkt antwortet für nicht angemeldete
  Besucher*innen mit 404. Bestehende Kalender-Abos bekommen also nichts, solange
  die Timetable ein Entwurf ist.
- **Veröffentlicht:** iCal-Download und Kalender-Abo sind verfügbar.

Kopien einer Timetable werden immer als Entwurf angelegt.

## Kalender-Abo (iCal)

Unter der Beschreibung steht ein Abo-Link für alle Termine, in der GANTT-Ansicht
je Bildungsgang die Links *iCal* (einmaliger Download) und *Abo*.

- Abo-Links nutzen `webcal://`. Outlook, Apple- und Google-Kalender laden den
  Kalender regelmäßig neu (Vorgabe: alle 6 Stunden), Änderungen kommen also
  automatisch bei den Kolleg*innen an.
- Jeder Termin hat eine stabile UID (`termin-<id>@<host>`). Eine Datumsänderung
  verschiebt den Termin im Kalender, statt ihn doppelt anzulegen.
- Hinweis zum Update auf 0.8.0: Wer vorher eine `.ics`-Datei *importiert* hat und
  sie erneut importiert, bekommt die Termine einmalig doppelt (die UIDs haben sich
  geändert). Empfehlung: alte Importe löschen und auf das Abo umsteigen.

## Timetable kopieren

*Timetables → Kopieren* legt eine Kopie mit allen Terminen an und verschiebt sie
so, dass der letzte Termin am gewählten Enddatum liegt.

- **Wochentagsgenau** (Standard): Verschiebung in ganzen Wochen, Wochentage
  bleiben erhalten. Das Ende kann dafür bis zu 3 Tage vom Wunschdatum abweichen.
- **Exakt:** Verschiebung um die genaue Anzahl Tage.
- **Schultage beachten** (Standard): Beginnt ein Termin nach der Verschiebung in
  Ferien, an einem Feiertag oder am Wochenende, wird er mit gleicher Dauer auf den
  nächsten Schultag gelegt. Alle Verschiebungen werden danach aufgelistet.
  Dafür müssen die Ferien des Zielzeitraums unter *Einstellungen* hinterlegt sein,
  sonst erscheint eine Warnung.

## Hinweise zu Terminen (Konfliktwarnungen)

Nach dem Speichern und in der Spalte ⚠ der Terminliste (Tooltip; Filter
„nur mit Hinweisen“) erscheinen Hinweise. Sie blockieren das Speichern nicht:

- Beginn oder Ende an einem Wochenende, in den Ferien oder an einem Feiertag
- Überschneidung mit einem Termin **anderen** Typs im selben Bildungsgang
  (mehrere Termine gleichen Typs am selben Tag, z. B. Konferenzen je Klasse, sind normal)
- mögliche Dublette: gleicher Typ, gleiche Bezeichnung, gleiche Daten
- Verstoß gegen eine abhängige Frist

## Abhängige Fristen

*Einstellungen → Abhängige Fristen* definiert Regeln zwischen Ereignistypen, z. B.

- „NE endet spätestens 3 Schultage vor Beginn von ZK“
- „ZA beginnt frühestens 1 Schultag nach Ende von ZK“

Regeln gelten je Timetable und Bildungsgang. Maßgeblich ist bei „vor“ der
**früheste**, bei „nach“ der **späteste** Bezugstermin, also die erste bzw. letzte
Konferenz des Durchgangs. Verschiebt sich dieser Termin (einzeln oder per
Mehrfachänderung), wandern die abhängigen Termine mit. Ihr bisheriger Abstand in
Schultagen bleibt erhalten, unterschreitet aber nie den Mindestabstand. Ketten
(z. B. ZK → ZA → APA) werden bis zu 5 Stufen weit nachgezogen. Jede
Mitverschiebung wird nach dem Speichern aufgelistet.

## Datenmodell

| Tabelle | Inhalt |
|---|---|
| `wp_tt_timetable` | Zeittafeln inkl. `status` (`entwurf` / `veroeffentlicht`) |
| `wp_tt_termine` | Termine; Bildungsgang und Ereignistyp als ID (`bildungsgang_id`, `ereignistyp_id`), das Kürzel zusätzlich als Fallback |
| `wp_tt_ferien` | Ferien und Feiertage (`typ` = `Ferien` oder `Feiertag`) |
| `wp_tt_bildungsgang` | Bildungsgänge (`kuerzel` eindeutig, `bezeichnung`, `aktiv`) |
| `wp_tt_bildungsgang_person` | Zuordnung Bildungsgang ↔ WordPress-Benutzer*in mit `rolle` (`al` / `bga`) |
| `wp_tt_ereignistyp` | Ereignistypen (`kuerzel` eindeutig, `bezeichnung`, `farbe`, `aktiv`) |

Bis 0.8 waren Bildungsgänge und Ereignistypen WordPress-Taxonomien. Die
Migration auf 0.9.0 übernimmt sie mit denselben IDs; die alten Terms bleiben als
Sicherung in der Datenbank. Wird ein Kürzel umbenannt, zeigen alle Termine
sofort den neuen Namen.

## Entwicklung

- Code-Struktur: `includes/` (PSR-4, Namespace `MH\Timetable`) mit Controller,
  Model (Entities, Repositories), Service, Viewer und Install (Schema/Migrationen).
- Composer wird nur für den Autoloader genutzt (`composer dump-autoload -o`);
  ohne `vendor/` greift ein eingebauter Autoloader.
- Schema-Änderungen: `Install\Schema::DB_VERSION` erhöhen (Struktur per dbDelta),
  Datenmigrationen über `DATA_VERSION` und eine neue `migrateToN()`-Methode.

### Release

1. Version in `timetable.php` anpassen (Header und `MH_TT_VERSION`), committen.
2. ZIP bauen:
   `git archive --prefix=wp-timetable/ -o ../wp-timetable_vX.Y.Z.zip HEAD`
3. Pushen und taggen.
