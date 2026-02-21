<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Entity;

use DateTime;

class Ferien
{
    private ?int $id = null;
    private string $name;
    private DateTime $startdatum;
    private DateTime $enddatum;
    private string $typ;

    public function __construct(
        string $name,
        DateTime $startdatum,
        DateTime $enddatum,
        string $typ
    ) {
        $this->name = $name;
        $this->startdatum = $startdatum;
        $this->enddatum = $enddatum;
        $this->typ = $typ;
    }

    // Getter & Setter
    public function getId(): ?int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function getName(): string { return $this->name; }
    public function getStartdatum(): DateTime { return $this->startdatum; }
    public function getEnddatum(): DateTime { return $this->enddatum; }
    public function getTyp(): string { return $this->typ; }
}