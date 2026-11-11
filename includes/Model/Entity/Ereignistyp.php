<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Entity;

/**
 * Ein Ereignistyp (z. B. ZK – Zeugniskonferenz) mit Anzeigefarbe.
 */
class Ereignistyp
{
    private ?int $id = null;
    private string $kuerzel;
    private string $bezeichnung;
    private string $farbe;
    private bool $aktiv;

    public function __construct(string $kuerzel, string $bezeichnung = '', string $farbe = '#cccccc', bool $aktiv = true)
    {
        $this->kuerzel = $kuerzel;
        $this->bezeichnung = $bezeichnung;
        $this->setFarbe($farbe);
        $this->aktiv = $aktiv;
    }

    public function getId(): ?int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function getKuerzel(): string { return $this->kuerzel; }
    public function setKuerzel(string $kuerzel): void { $this->kuerzel = $kuerzel; }
    public function getBezeichnung(): string { return $this->bezeichnung; }
    public function setBezeichnung(string $bezeichnung): void { $this->bezeichnung = $bezeichnung; }
    public function getFarbe(): string { return $this->farbe; }
    public function setFarbe(string $farbe): void
    {
        $this->farbe = (preg_match('/^#[0-9a-fA-F]{6}$/', $farbe) === 1) ? strtolower($farbe) : '#cccccc';
    }
    public function isAktiv(): bool { return $this->aktiv; }
    public function setAktiv(bool $aktiv): void { $this->aktiv = $aktiv; }

    public function getAnzeigename(): string
    {
        return $this->bezeichnung !== '' ? $this->kuerzel . ' – ' . $this->bezeichnung : $this->kuerzel;
    }
}
