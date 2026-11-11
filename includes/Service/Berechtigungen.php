<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

use MH\Timetable\Model\Entity\Bildungsgang;
use MH\Timetable\Model\Entity\Termin;
use MH\Timetable\Model\Repository\WpdbBildungsgangRepository;

/**
 * Zentrale Rechteprüfung. Jede Änderung an Terminen – egal ob aus dem Backend
 * oder aus dem Frontend – wird hier entschieden.
 *
 *  - Admins (manage_options) dürfen alles.
 *  - Abteilungsleitung (AL) und Bildungsgangassistenz (BGA) dürfen Termine
 *    ihrer Bildungsgänge anlegen, ändern und löschen – sonst nichts.
 */
class Berechtigungen
{
    public const CAP_ADMIN = 'manage_options';

    private WpdbBildungsgangRepository $bildungsgaenge;

    public function __construct(WpdbBildungsgangRepository $bildungsgaenge)
    {
        $this->bildungsgaenge = $bildungsgaenge;
    }

    public function istAdmin(int $userId): bool
    {
        return $userId > 0 && user_can($userId, self::CAP_ADMIN);
    }

    /** Darf die Person Termine dieses Bildungsgangs (Kürzel) bearbeiten? */
    public function darfBildungsgang(int $userId, string $bildungsgangKuerzel): bool
    {
        if ($this->istAdmin($userId)) return true;
        $bg = $this->bildungsgaenge->findByKuerzel($bildungsgangKuerzel);
        return $bg !== null && $bg->darfBearbeiten($userId);
    }

    public function darfTermin(int $userId, Termin $termin): bool
    {
        return $this->darfBildungsgang($userId, $termin->getBildungsgang());
    }

    /**
     * Bildungsgänge, deren Termine die Person bearbeiten darf.
     * @return Bildungsgang[]
     */
    public function bearbeitbareBildungsgaenge(int $userId): array
    {
        if ($userId <= 0) return [];
        if ($this->istAdmin($userId)) return $this->bildungsgaenge->getAll();
        return array_values(array_filter($this->bildungsgaenge->getAll(), fn(Bildungsgang $bg) => $bg->darfBearbeiten($userId)));
    }

    /** Hat die Person überhaupt Bearbeitungsrechte (Admin, AL oder BGA)? */
    public function istBearbeiter(int $userId): bool
    {
        return $this->istAdmin($userId) || ($userId > 0 && !empty($this->bildungsgaenge->getRollenFuerUser($userId)));
    }
}
