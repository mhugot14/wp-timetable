<?php
declare(strict_types=1);

namespace MH\Timetable\Install;

/**
 * Legt die Datenbanktabellen an bzw. aktualisiert sie per dbDelta und führt
 * anschließend nötige Datenmigrationen aus.
 *
 * Ablauf:
 *  - plugins_loaded: Struktur (dbDelta), sobald DB_VERSION sich geändert hat.
 *  - init (nach der Taxonomie-Registrierung): Datenmigrationen, sobald
 *    DATA_VERSION sich geändert hat. Diese brauchen die Taxonomien.
 */
class Schema
{
    public const DB_VERSION = '2.1';
    public const DATA_VERSION = '2';
    public const OPTION_DB_VERSION = 'mh_tt_db_version';
    public const OPTION_DATA_VERSION = 'mh_tt_data_version';

    /** Maximale Feldlängen – werden auch für Formulare und Validierung genutzt. */
    public const LEN_TERMIN_BEZEICHNUNG    = 150;
    public const LEN_TERMIN_BILDUNGSGANG   = 100;
    public const LEN_TERMIN_EREIGNISTYP    = 50;
    public const LEN_TERMIN_VERANTWORTLICH = 100;
    public const LEN_TIMETABLE_BEZEICHNUNG = 100;
    public const LEN_TIMETABLE_BESCHREIBUNG = 255;

    public static function maybeUpgrade(): void
    {
        if (get_option(self::OPTION_DB_VERSION) !== self::DB_VERSION) {
            self::install();
        }
    }

    public static function install(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();

        $termine   = $wpdb->prefix . 'tt_termine';
        $timetable = $wpdb->prefix . 'tt_timetable';
        $ferien    = $wpdb->prefix . 'tt_ferien';

        // dbDelta-Format: ein Feld pro Zeile, zwei Leerzeichen nach PRIMARY KEY,
        // Indizes als "KEY name (spalte)".
        $sql = [];
        $sql[] = "CREATE TABLE $termine (
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
) $charsetCollate;";

        $sql[] = "CREATE TABLE $timetable (
  id int(10) NOT NULL AUTO_INCREMENT,
  bezeichnung varchar(" . self::LEN_TIMETABLE_BEZEICHNUNG . ") NOT NULL,
  beschreibung varchar(" . self::LEN_TIMETABLE_BESCHREIBUNG . ") NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'veroeffentlicht',
  erzeugt_am timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id)
) $charsetCollate;";

        $sql[] = "CREATE TABLE $ferien (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  startdatum date NOT NULL,
  enddatum date NOT NULL,
  typ varchar(20) NOT NULL DEFAULT 'Ferien',
  PRIMARY KEY  (id),
  KEY startdatum (startdatum)
) $charsetCollate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
    }

    /**
     * Datenmigrationen – laufen auf "init" nach der Taxonomie-Registrierung.
     */
    public static function maybeMigrateData(): void
    {
        $current = (int)get_option(self::OPTION_DATA_VERSION, 1);
        if ($current >= (int)self::DATA_VERSION) {
            return;
        }
        // Struktur muss vorher aktuell sein (Spalten *_id).
        if (get_option(self::OPTION_DB_VERSION) !== self::DB_VERSION) {
            return;
        }

        if ($current < 2) {
            self::migrateTo2();
        }

        update_option(self::OPTION_DATA_VERSION, self::DATA_VERSION);
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

    /** Liefert die Term-ID zu einem Namen und legt den Term bei Bedarf an. */
    public static function ensureTerm(string $name, string $taxonomy): int
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

    public static function deactivate(): void
    {
        // Alter Cron-Eintrag aus früheren Versionen ohne zugehörigen Handler.
        wp_clear_scheduled_hook('timetable/weekly_cron');
    }
}
