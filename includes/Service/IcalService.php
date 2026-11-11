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
        $ical .= $this->foldLine("X-WR-CALNAME:" . $this->escapeString($title));

        foreach ($termine as $termin) {
            $ical .= $this->renderEvent($termin);
        }

        $ical .= "END:VCALENDAR\r\n";
        return $ical;
    }

    private function renderEvent(Termin $termin): string
    {
        $uid = md5($termin->getId() . $termin->getBeginn()->format('Ymd'));
        $start = $termin->getBeginn()->format('Ymd');
        // iCal End-Datum ist exklusiv, daher +1 Tag für Ganztages-Events
        $ende = (clone $termin->getEnde())->modify('+1 day')->format('Ymd');

        $event = "BEGIN:VEVENT\r\n";
        $event .= "UID:" . $uid . "@mh-timetable.de\r\n";
        $event .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $event .= "DTSTART;VALUE=DATE:" . $start . "\r\n";
        $event .= "DTEND;VALUE=DATE:" . $ende . "\r\n";
        $event .= $this->foldLine("SUMMARY:" . $this->escapeString($termin->getBildungsgang() . ": " . $termin->getBezeichnung()));
        $event .= $this->foldLine("DESCRIPTION:" . $this->escapeString("Typ: " . $termin->getEreignistyp() . " | Verantwortlich: " . $termin->getVerantwortlich()));
        $event .= "END:VEVENT\r\n";

        return $event;
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