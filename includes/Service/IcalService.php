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
        $ical .= "X-WR-CALNAME:" . $title . "\r\n";

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
        $event .= "SUMMARY:" . $this->escapeString($termin->getBildungsgang() . ": " . $termin->getBezeichnung()) . "\r\n";
        $event .= "DESCRIPTION:" . $this->escapeString("Typ: " . $termin->getEreignistyp() . " | Verantwortlich: " . $termin->getVerantwortlich()) . "\r\n";
        $event .= "END:VEVENT\r\n";

        return $event;
    }

    private function escapeString(string $string): string
    {
        return str_replace([',', ';'], ['\,', '\;'], $string);
    }
}