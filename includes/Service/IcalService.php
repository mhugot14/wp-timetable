<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

use MH\Timetable\Model\Entity\Termin;

class IcalService
{
    /**
     * Generiert den Inhalt einer iCal-Datei für eine Liste von Terminen.
     * @param Termin[] $termine
     */
    public function generateString(array $termine, string $title): string
    {
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//MH Timetable Plugin//DE\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        // Abo-Clients (Outlook, Apple, Google) sollen alle 6 Stunden neu laden
        $ical .= "REFRESH-INTERVAL;VALUE=DURATION:PT6H\r\n";
        $ical .= "X-PUBLISHED-TTL:PT6H\r\n";
        $ical .= $this->foldLine("X-WR-CALNAME:" . $this->escapeString($title));

        foreach ($termine as $termin) {
            $ical .= $this->renderEvent($termin);
        }

        $ical .= "END:VCALENDAR\r\n";
        return $ical;
    }

    private function renderEvent(Termin $termin): string
    {
        // Stabile UID: nur von der Termin-ID abhängig. So erkennen Kalender-Abos eine
        // Datumsänderung als Aktualisierung desselben Termins statt als neuen Termin.
        $uid = 'termin-' . (int)$termin->getId() . '@' . $this->getHost();
        $start = $termin->getBeginn()->format('Ymd');
        // iCal End-Datum ist exklusiv, daher +1 Tag für Ganztages-Events
        $ende = (clone $termin->getEnde())->modify('+1 day')->format('Ymd');

        $event = "BEGIN:VEVENT\r\n";
        $event .= "UID:" . $uid . "\r\n";
        $event .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $event .= "DTSTART;VALUE=DATE:" . $start . "\r\n";
        $event .= "DTEND;VALUE=DATE:" . $ende . "\r\n";
        $event .= $this->foldLine("SUMMARY:" . $this->escapeString($termin->getBildungsgang() . ": " . $termin->getBezeichnung()));
        $event .= $this->foldLine("DESCRIPTION:" . $this->escapeString("Typ: " . $termin->getEreignistyp() . " | Verantwortlich: " . $termin->getVerantwortlich()));
        $event .= "END:VEVENT\r\n";

        return $event;
    }

    private function getHost(): string
    {
        $host = (string)wp_parse_url(home_url(), PHP_URL_HOST);
        return $host !== '' ? $host : 'localhost';
    }

    /**
     * Baut die URL zum Kalender einer Timetable (optional nur ein Bildungsgang).
     *
     * @param bool $abo true → webcal://-Link zum Abonnieren, false → https-Download
     */
    public static function buildUrl(int $timetableId, string $bildungsgang = '', bool $abo = false): string
    {
        $args = ['download_ical' => 1, 'timetable_id' => $timetableId];
        if ($bildungsgang !== '') {
            $args['bg'] = $bildungsgang;
        }
        if ($abo) {
            $args['abo'] = 1;
        }
        // rawurlencode statt "+" für Leerzeichen – manche Kalender-Clients mögen kein "+"
        $url = add_query_arg(array_map('rawurlencode', $args), home_url('/'));

        return $abo ? (string)preg_replace('#^https?://#i', 'webcal://', $url) : $url;
    }

    /**
     * Maskiert Text nach RFC 5545 (Backslash zuerst, dann Sonderzeichen und Umbrüche).
     */
    private function escapeString(string $string): string
    {
        $string = str_replace('\\', '\\\\', $string);
        $string = str_replace([',', ';'], ['\,', '\;'], $string);
        return str_replace(["\r\n", "\r", "\n"], '\n', $string);
    }

    /**
     * Bricht eine Zeile nach max. 75 Bytes um (RFC 5545, Abschnitt 3.1),
     * ohne UTF-8-Zeichen zu zerteilen. Liefert die Zeile inkl. CRLF.
     */
    private function foldLine(string $line): string
    {
        $out = '';
        $limit = 75;
        while (strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $out .= $chunk . "\r\n ";
            $line = substr($line, strlen($chunk));
            $limit = 74; // Folgezeilen beginnen mit einem Leerzeichen
        }
        return $out . $line . "\r\n";
    }
}