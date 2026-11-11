<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Entity;

/**
 * Ein Bildungsgang mit Abteilungsleitung (AL) und Bildungsgangassistenzen (BGA).
 * AL und BGA dürfen die Termine dieses Bildungsgangs bearbeiten.
 */
class Bildungsgang
{
    public const ROLLE_AL  = 'al';
    public const ROLLE_BGA = 'bga';

    public const ROLLEN_LABELS = [
        self::ROLLE_AL  => 'Abteilungsleitung',
        self::ROLLE_BGA => 'Bildungsgangassistenz',
    ];

    private ?int $id = null;
    private string $kuerzel;
    private string $bezeichnung;
    private bool $aktiv;
    private ?int $alUserId = null;
    /** @var int[] */
    private array $bgaUserIds = [];

    public function __construct(string $kuerzel, string $bezeichnung = '', bool $aktiv = true)
    {
        $this->kuerzel = $kuerzel;
        $this->bezeichnung = $bezeichnung;
        $this->aktiv = $aktiv;
    }

    public function getId(): ?int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function getKuerzel(): string { return $this->kuerzel; }
    public function setKuerzel(string $kuerzel): void { $this->kuerzel = $kuerzel; }
    public function getBezeichnung(): string { return $this->bezeichnung; }
    public function setBezeichnung(string $bezeichnung): void { $this->bezeichnung = $bezeichnung; }
    public function isAktiv(): bool { return $this->aktiv; }
    public function setAktiv(bool $aktiv): void { $this->aktiv = $aktiv; }

    public function getAlUserId(): ?int { return $this->alUserId; }
    public function setAlUserId(?int $userId): void { $this->alUserId = $userId ?: null; }

    /** @return int[] */
    public function getBgaUserIds(): array { return $this->bgaUserIds; }
    /** @param int[] $userIds */
    public function setBgaUserIds(array $userIds): void
    {
        $this->bgaUserIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    }

    /** Darf diese Person die Termine des Bildungsgangs bearbeiten? */
    public function darfBearbeiten(int $userId): bool
    {
        return $userId > 0 && ($this->alUserId === $userId || in_array($userId, $this->bgaUserIds, true));
    }

    /** Anzeige „Kürzel – Bezeichnung“ bzw. nur Kürzel */
    public function getAnzeigename(): string
    {
        return $this->bezeichnung !== '' ? $this->kuerzel . ' – ' . $this->bezeichnung : $this->kuerzel;
    }
}
