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
| `id` | ID der Timetable (siehe Spalte *ID* unter *Timetables*) |
| `entwurf` | `ja` zeigt den Hinweis „Entwurf – noch nicht verbindlich“ und blendet die iCal-Links aus |

## Datenmodell

| Tabelle | Inhalt |
|---|---|
| `wp_tt_timetable` | Zeittafeln |
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
