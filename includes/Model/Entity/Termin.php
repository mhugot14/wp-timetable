<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Entity;

use DateTime;

/**
 * Die Termin-Entity repräsentiert einen einzelnen Datensatz.
 * Sie enthält reine Daten und fachliche Logik (wie die Dauer),
 * aber keine Datenbank-Operationen.
 */
class Termin
{
    private ?int $id = null;
    private DateTime $beginn;
    private DateTime $ende;
    private string $bildungsgang;
    private string $bezeichnung;
    private string $ereignistyp;
    private string $verantwortlich;
    private int $timetableId;

    public function __construct(
        DateTime $beginn,
        DateTime $ende,
        string $bildungsgang,
        string $bezeichnung,
        string $ereignistyp,
        string $verantwortlich,
        int $timetableId
    ) {
        $this->beginn = $beginn;
        $this->ende = $ende;
        $this->bildungsgang = $bildungsgang;
        $this->bezeichnung = $bezeichnung;
        $this->ereignistyp = $ereignistyp;
        $this->verantwortlich = $verantwortlich;
        $this->timetableId = $timetableId;
    }

    /**
     * Berechnet die Dauer des Termins in Tagen.
     * Fachliche Logik gehört direkt in die Entity.
     */
    public function getDauer(): int
    {
        $diff = $this->beginn->diff($this->ende);
        return (int)$diff->days + 1;
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

    public function getBeginn(): DateTime
    {
        return $this->beginn;
    }

    public function setBeginn(DateTime $beginn): void
    {
        $this->beginn = $beginn;
    }

    public function getEnde(): DateTime
    {
        return $this->ende;
    }

    public function setEnde(DateTime $ende): void
    {
        $this->ende = $ende;
    }

    public function getBildungsgang(): string
    {
        return $this->bildungsgang;
    }

    public function setBildungsgang(string $bildungsgang): void
    {
        $this->bildungsgang = $bildungsgang;
    }

    public function getBezeichnung(): string
    {
        return $this->bezeichnung;
    }

    public function setBezeichnung(string $bezeichnung): void
    {
        $this->bezeichnung = $bezeichnung;
    }

    public function getEreignistyp(): string
    {
        return $this->ereignistyp;
    }

    public function setEreignistyp(string $ereignistyp): void
    {
        $this->ereignistyp = $ereignistyp;
    }

    public function getVerantwortlich(): string
    {
        return $this->verantwortlich;
    }

    public function setVerantwortlich(string $verantwortlich): void
    {
        $this->verantwortlich = $verantwortlich;
    }

    public function getTimetableId(): int
    {
        return $this->timetableId;
    }

    public function setTimetableId(int $timetableId): void
    {
        $this->timetableId = $timetableId;
    }

    public function __clone()
    {
        if (isset($this->beginn)) {
            $this->beginn = clone $this->beginn;
        }
        if (isset($this->ende)) {
            $this->ende = clone $this->ende;
        }
        $this->id = null; // Die ID muss bei einer Kopie immer genullt werden
    }
}