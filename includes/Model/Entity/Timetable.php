<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Entity;

use DateTime;

/**
 * Die Timetable-Entity.
 * Ein reiner Datenträger für eine Zeittafel.
 */
class Timetable
{
    private ?int $id = null;
    private string $bezeichnung = '';
    private string $beschreibung = '';
    private DateTime $erzeugtAm;
    
    /** @var Termin[] */
    private array $termine = [];

    public function __construct()
    {
        $this->erzeugtAm = new DateTime();
    }

    /**
     * Berechnet das früheste Datum aller verknüpften Termine.
     */
    public function getEarliestDate(): ?DateTime
    {
        if (empty($this->termine)) {
            return null;
        }

        $earliest = null;
        foreach ($this->termine as $termin) {
            $current = $termin->getBeginn();
            if ($earliest === null || $current < $earliest) {
                $earliest = $current;
            }
        }
        return $earliest;
    }

    /**
     * Berechnet das späteste Datum aller verknüpften Termine.
     */
    public function getLastDate(): ?DateTime
    {
        if (empty($this->termine)) {
            return null;
        }

        $last = null;
        foreach ($this->termine as $termin) {
            $current = $termin->getEnde();
            if ($last === null || $current > $last) {
                $last = $current;
            }
        }
        return $last;
    }

    /**
     * Berechnet die Gesamtlänge der Timetable in Tagen.
     */
    public function getLaengeInTagen(): int
    {
        $start = $this->getEarliestDate();
        $ende = $this->getLastDate();

        if (!$start || !$ende) {
            return 0;
        }

        return (int)$start->diff($ende)->days + 1;
    }

    /**
     * Liefert alle einzelnen Tage innerhalb des Zeitraums zurück.
     * @return DateTime[]
     */
    public function getDateRange(): array
    {
        $start = $this->getEarliestDate();
        $ende = $this->getLastDate();
        
        if (!$start || !$ende) {
            return [];
        }

        $dates = [];
        $current = clone $start;
        while ($current <= $ende) {
            $dates[] = clone $current;
            $current->modify('+1 day');
        }
        return $dates;
    }

    // --- Getter & Setter ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getBezeichnung(): string
    {
        return $this->bezeichnung;
    }

    public function setBezeichnung(string $bezeichnung): void
    {
        $this->bezeichnung = $bezeichnung;
    }

    public function getBeschreibung(): string
    {
        return $this->beschreibung;
    }

    public function setBeschreibung(string $beschreibung): void
    {
        $this->beschreibung = $beschreibung;
    }

    public function getErzeugtAm(): DateTime
    {
        return $this->erzeugtAm;
    }

    public function setErzeugtAm(DateTime $erzeugtAm): void
    {
        $this->erzeugtAm = $erzeugtAm;
    }

    /** @return Termin[] */
    public function getTermine(): array
    {
        return $this->termine;
    }

    /** @param Termin[] $termine */
    public function setTermine(array $termine): void
    {
        $this->termine = $termine;
    }
}