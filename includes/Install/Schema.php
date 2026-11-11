<?php
declare(strict_types=1);

namespace MH\Timetable\Install;

/**
 * Legt die Datenbanktabellen an bzw. aktualisiert sie per dbDelta.
 *
 * Läuft bei der Plugin-Aktivierung und zusätzlich bei jedem Laden, sobald
 * die gespeicherte DB-Version nicht zur aktuellen passt (z. B. nach einem
 * Update per ZIP-Upload, bei dem kein Aktivierungs-Hook feuert).
 */
class Schema
{
    public const DB_VERSION = '1.12';
    public const OPTION_DB_VERSION = 'mh_tt_db_version';

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

        // dbDelta-Format: ein Feld pro Zeile, zwei Leerzeichen nach PRIMARY KEY.
        $sql = [];
        $sql[] = "CREATE TABLE $termine (
  id int(20) NOT NULL AUTO_INCREMENT,
  bildungsgang varchar(30) NOT NULL,
  bezeichnung varchar(50) NOT NULL,
  ereignistyp varchar(20) NOT NULL,
  beginn date NOT NULL,
  ende date NOT NULL,
  verantwortlich varchar(30) NOT NULL,
  timetable_ID int(10) NOT NULL,
  PRIMARY KEY  (id)
) $charsetCollate;";

        $sql[] = "CREATE TABLE $timetable (
  id int(10) NOT NULL AUTO_INCREMENT,
  bezeichnung varchar(30) NOT NULL,
  beschreibung varchar(100) NOT NULL,
  erzeugt_am timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id)
) $charsetCollate;";

        $sql[] = "CREATE TABLE $ferien (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  startdatum date NOT NULL,
  enddatum date NOT NULL,
  typ enum('ferien','feiertag') NOT NULL,
  PRIMARY KEY  (id)
) $charsetCollate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
    }

    public static function deactivate(): void
    {
        // Alter Cron-Eintrag aus früheren Versionen ohne zugehörigen Handler.
        wp_clear_scheduled_hook('timetable/weekly_cron');
    }
}
