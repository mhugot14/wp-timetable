<?php
declare(strict_types=1);

namespace MH\Timetable\Install;

/**
 * Legt die Datenbanktabellen an bzw. aktualisiert sie per dbDelta und führt
 * anschließend nötige Datenmigrationen aus.
 *
 * Ablauf:
 *  - plugins_loaded: Struktur (dbDelta), sobald DB_VERSION sich geändert hat.
 *  - init (Priorität 20): Datenmigrationen, sobald DATA_VERSION sich geändert hat.
 *
 * Datenversionen:
 *  2 – Termine verweisen per ID auf Bildungsgang/Ereignistyp (damals Taxonomie-Terms)
 *  3 – Bildungsgänge und Ereignistypen in eigenen Tabellen (IDs = alte Term-IDs)
 */
class Schema
{
    public const DB_VERSION = '3.0';
    public const DATA_VERSION = '3';
    public const OPTION_DB_VERSION = 'mh_tt_db_version';
    public const OPTION_DATA_VERSION = 'mh_tt_data_version';

    /** Maximale Feldlängen – werden auch für Formulare und Validierung genutzt. */
    public const LEN_TERMIN_BEZEICHNUNG    = 150;
    public const LEN_TERMIN_BILDUNGSGANG   = 100;
    public const LEN_TERMIN_EREIGNISTYP    = 50;
    public const LEN_TERMIN_VERANTWORTLICH = 100;
    public const LEN_TIMETABLE_BEZEICHNUNG = 100;
    public const LEN_TIMETABLE_BESCHREIBUNG = 255;
    public const LEN_STAMM_KUERZEL         = 50;
    public const LEN_STAMM_BEZEICHNUNG     = 150;

    /** Standard-Ereignistypen für Neuinstallationen */
    public const DEFAULT_EREIGNISTYPEN = [
        ['ZK',  'Zeugniskonferenz',              '#704E2E'],
        ['ZA',  'Zeugnisausgabe',                '#2E4756'],
        ['NE',  'Noteneingabe',                  '#7BB7AF'],
        ['APA', 'Allgemeiner Prüfungsausschuss', '#B98DA0'],
    ];

    public static function maybeUpgrade(): void
    {
        if (get_option(self::OPTION_DB_VERSION) !== self::DB_VERSION) {
            self::install();
        }
    }

    public static function install(): void
    {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        // dbDelta-Format: ein Feld pro Zeile, zwei Leerzeichen nach PRIMARY KEY,
        // Indizes als "KEY name (spalte)".
        $sql = [];
        $sql[] = "CREATE TABLE {$p}tt_termine (
  id int(20) NOT NULL AUTO_INCREMENT,
  bildungsgang varchar(" . self::LEN_TERMIN_BILDUNGSGANG . ") NOT NULL,
  bildungsgang_id bigint(20) unsigned DEFAULT NULL,
  bezeichnung varchar(" . self::LEN_TERMIN_BEZEICHNUNG . ") NOT NULL,
  ereignistyp varchar(" . self::LEN_TERMIN_EREIGNISTYP . ") NOT NULL,
  ereignistyp_id bigint(20) unsigned DEFAULT NULL,
  beginn date NOT NULL,
  ende date NOT NULL,
  verantwortlich varchar(" . self::LEN_TERMIN_VERANTWORTLICH . ") NOT NULL DEFAULT '',
  timetable_ID int(10) NOT NULL,
  PRIMARY KEY  (id),
  KEY timetable_id (timetable_ID),
  KEY beginn (beginn),
  KEY bildungsgang_id (bildungsgang_id),
  KEY ereignistyp_id (ereignistyp_id)
) $c;";

        $sql[] = "CREATE TABLE {$p}tt_timetable (
  id int(10) NOT NULL AUTO_INCREMENT,
  bezeichnung varchar(" . self::LEN_TIMETABLE_BEZEICHNUNG . ") NOT NULL,
  beschreibung varchar(" . self::LEN_TIMETABLE_BESCHREIBUNG . ") NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'veroeffentlicht',
  erzeugt_am timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id)
) $c;";

        $sql[] = "CREATE TABLE {$p}tt_ferien (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  startdatum date NOT NULL,
  enddatum date NOT NULL,
  typ varchar(20) NOT NULL DEFAULT 'Ferien',
  PRIMARY KEY  (id),
  KEY startdatum (startdatum)
) $c;";

        $sql[] = "CREATE TABLE {$p}tt_bildungsgang (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kuerzel varchar(" . self::LEN_STAMM_KUERZEL . ") NOT NULL,
  bezeichnung varchar(" . self::LEN_STAMM_BEZEICHNUNG . ") NOT NULL DEFAULT '',
  aktiv tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY kuerzel (kuerzel)
) $c;";

        // Rolle: 'al' = Abteilungsleitung (höchstens eine je Bildungsgang),
        //        'bga' = Bildungsgangassistenz (beliebig viele)
        $sql[] = "CREATE TABLE {$p}tt_bildungsgang_person (
  bildungsgang_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  rolle varchar(10) NOT NULL,
  PRIMARY KEY  (bildungsgang_id,user_id),
  KEY user_id (user_id)
) $c;";

        $sql[] = "CREATE TABLE {$p}tt_ereignistyp (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kuerzel varchar(" . self::LEN_STAMM_KUERZEL . ") NOT NULL,
  bezeichnung varchar(" . self::LEN_STAMM_BEZEICHNUNG . ") NOT NULL DEFAULT '',
  farbe varchar(7) NOT NULL DEFAULT '#cccccc',
  aktiv tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY kuerzel (kuerzel)
) $c;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
    }

    /**
     * Datenmigrationen – laufen auf "init" (Priorität 20).
     */
    public static function maybeMigrateData(): void
    {
        $current = (int)get_option(self::OPTION_DATA_VERSION, 1);
        if ($current >= (int)self::DATA_VERSION) {
            return;
        }
        // Struktur muss vorher aktuell sein.
        if (get_option(self::OPTION_DB_VERSION) !== self::DB_VERSION) {
            return;
        }

        if ($current < 2) {
            self::migrateTo2();
        }
        if ($current < 3) {
            self::migrateTo3();
        }

        update_option(self::OPTION_DATA_VERSION, self::DATA_VERSION);
    }

    /**
     * Die Taxonomien werden seit 0.9.0 nicht mehr registriert. Ältere Migrationen
     * brauchen sie aber noch – dann hier vorübergehend registrieren.
     */
    private static function registerLegacyTaxonomies(): void
    {
        foreach (['bildungsgang', 'ereignistyp'] as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                register_taxonomy($taxonomy, [], ['public' => false, 'rewrite' => false, 'show_ui' => false]);
            }
        }
    }

    /**
     * v2: Termine auf Term-IDs umstellen, Ferien-Typ vereinheitlichen.
     * Fehlende Terms werden angelegt, damit kein Termin seine Zuordnung verliert.
     */
    private static function migrateTo2(): void
    {
        global $wpdb;
        $termine = $wpdb->prefix . 'tt_termine';
        $ferien  = $wpdb->prefix . 'tt_ferien';

        self::registerLegacyTaxonomies();

        foreach (['bildungsgang' => 'bildungsgang_id', 'ereignistyp' => 'ereignistyp_id'] as $taxonomy => $idColumn) {
            $names = $wpdb->get_col("SELECT DISTINCT $taxonomy FROM $termine WHERE $idColumn IS NULL AND $taxonomy <> ''");
            foreach ($names as $name) {
                $termId = self::ensureTerm((string)$name, $taxonomy);
                if ($termId > 0) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $termine SET $idColumn = %d WHERE $taxonomy = %s AND $idColumn IS NULL",
                        $termId,
                        $name
                    ));
                }
            }
        }

        // Ferien-Typ: aus dem alten ENUM kamen kleingeschriebene Werte.
        $wpdb->query("UPDATE $ferien SET typ = 'Ferien' WHERE LOWER(typ) = 'ferien'");
        $wpdb->query("UPDATE $ferien SET typ = 'Feiertag' WHERE LOWER(typ) = 'feiertag'");
    }

    /** Liefert die Term-ID zu einem Namen und legt den Term bei Bedarf an (nur Migration v2). */
    private static function ensureTerm(string $name, string $taxonomy): int
    {
        $existing = term_exists($name, $taxonomy);
        if ($existing) {
            return (int)(is_array($existing) ? $existing['term_id'] : $existing);
        }
        $created = wp_insert_term($name, $taxonomy);
        if (is_wp_error($created)) {
            error_log('MH Timetable: Term konnte nicht angelegt werden: ' . $name . ' (' . $created->get_error_message() . ')');
            return 0;
        }
        return (int)$created['term_id'];
    }

    /**
     * v3: Bildungsgänge und Ereignistypen aus den Taxonomien in eigene Tabellen
     * übernehmen. Die IDs bleiben gleich (ID = alte Term-ID) – damit bleiben alle
     * Verweise in Terminen und Fristen-Regeln ohne Umrechnung gültig.
     * Die Terms selbst bleiben als Sicherung in der Datenbank stehen.
     */
    private static function migrateTo3(): void
    {
        global $wpdb;
        $p = $wpdb->prefix;

        foreach (['bildungsgang' => "{$p}tt_bildungsgang", 'ereignistyp' => "{$p}tt_ereignistyp"] as $taxonomy => $tabelle) {
            $terms = $wpdb->get_results($wpdb->prepare(
                "SELECT t.term_id, t.name, tt.description
                   FROM {$wpdb->terms} t
                   JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
                  WHERE tt.taxonomy = %s",
                $taxonomy
            ));
            foreach ($terms as $term) {
                if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabelle WHERE id = %d", $term->term_id))) {
                    continue;
                }
                $daten = [
                    'id'          => (int)$term->term_id,
                    // WordPress speichert "&" in Term-Namen als "&amp;"
                    'kuerzel'     => mb_substr(html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'), 0, self::LEN_STAMM_KUERZEL),
                    'bezeichnung' => mb_substr(html_entity_decode((string)$term->description, ENT_QUOTES, 'UTF-8'), 0, self::LEN_STAMM_BEZEICHNUNG),
                    'aktiv'       => 1,
                ];
                if ($taxonomy === 'ereignistyp') {
                    $farbe = sanitize_hex_color((string)$wpdb->get_var($wpdb->prepare(
                        "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = 'ereignisfarbe'",
                        $term->term_id
                    )));
                    $daten['farbe'] = $farbe ?: '#cccccc';
                }
                if (!$wpdb->insert($tabelle, $daten)) {
                    // Kürzel schon vorhanden (z. B. nur Groß-/Kleinschreibung verschieden):
                    // Termine dieses Terms auf den vorhandenen Eintrag umhängen.
                    $vorhanden = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $tabelle WHERE kuerzel = %s", $daten['kuerzel']));
                    if ($vorhanden) {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$p}tt_termine SET {$taxonomy}_id = %d WHERE {$taxonomy}_id = %d",
                            $vorhanden,
                            $term->term_id
                        ));
                    }
                }
            }
        }

        // Neuinstallation ohne Terms: Standard-Ereignistypen anlegen
        if (!(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}tt_ereignistyp")) {
            foreach (self::DEFAULT_EREIGNISTYPEN as [$kuerzel, $bezeichnung, $farbe]) {
                $wpdb->insert("{$p}tt_ereignistyp", ['kuerzel' => $kuerzel, 'bezeichnung' => $bezeichnung, 'farbe' => $farbe]);
            }
        }

        // Termine ohne gültige ID (z. B. gelöschter Term) über das Kürzel zuordnen.
        // Bewusst in PHP statt per JOIN über zwei Tabellen – sonst droht bei
        // unterschiedlichen Tabellen-Kollationen "Illegal mix of collations".
        foreach (['bildungsgang', 'ereignistyp'] as $feld) {
            $ohne = $wpdb->get_col(
                "SELECT DISTINCT {$feld} FROM {$p}tt_termine
                  WHERE {$feld}_id IS NULL
                     OR {$feld}_id NOT IN (SELECT id FROM {$p}tt_{$feld})"
            );
            foreach ($ohne as $name) {
                $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}tt_{$feld} WHERE kuerzel = %s", $name));
                if (!$id && $name !== '') {
                    $wpdb->insert("{$p}tt_{$feld}", ['kuerzel' => mb_substr((string)$name, 0, self::LEN_STAMM_KUERZEL)]);
                    $id = (int)$wpdb->insert_id;
                }
                if ($id) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$p}tt_termine SET {$feld}_id = %d
                          WHERE {$feld} = %s AND ({$feld}_id IS NULL OR {$feld}_id NOT IN (SELECT id FROM {$p}tt_{$feld}))",
                        $id,
                        $name
                    ));
                }
            }
        }
    }

    public static function deactivate(): void
    {
        // Alter Cron-Eintrag aus früheren Versionen ohne zugehörigen Handler.
        wp_clear_scheduled_hook('timetable/weekly_cron');
    }
}
