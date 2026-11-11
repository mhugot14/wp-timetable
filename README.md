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
| **Einstellungen** | Bildungsgänge und Ereignistypen (inkl. Farbe) verwalten, Ferien und Feiertage manuell oder per API importieren |

## Shortcode

```
[insertTimetable id="12"]
[insertTimetable id="12" entwurf="ja"]
```

| Attribut | Bedeutung |
|---|---|
| `id` | ID der Timetable (den fertigen Shortcode zeigt die Spalte *Shortcode* unter *Timetables*) |
| `entwurf` | veraltet – besser den **Status** der Timetable auf *Entwurf* setzen. `ja` wirkt weiterhin wie der Status *Entwurf*. |

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
| `wp_tt_termine` | Termine; Bildungsgang und Ereignistyp als Term-ID (`bildungsgang_id`, `ereignistyp_id`), der Name wird zusätzlich als Fallback gespeichert |
| `wp_tt_ferien` | Ferien und Feiertage (`typ` = `Ferien` oder `Feiertag`) |

Bildungsgänge und Ereignistypen sind WordPress-Taxonomien (`bildungsgang`,
`ereignistyp`). Wird ein Term umbenannt, zeigen alle Termine sofort den neuen
Namen. Die Farbe eines Ereignistyps liegt in der Term-Meta `ereignisfarbe`.

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
