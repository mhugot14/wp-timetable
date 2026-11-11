<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

use MH\Timetable\Model\Repository\FerienRepositoryInterface;
use DateTime;

/**
 * Beantwortet Fragen zu Schultagen: Wochenende, Ferien, Feiertage.
 * Die Ferien werden beim ersten Zugriff einmal geladen und als Tages-Map gehalten.
 */
class SchulkalenderService
{
    private FerienRepositoryInterface $ferienRepository;

    /** @var array<string,string>|null Y-m-d → Name der Ferien/des Feiertags */
    private ?array $freieTage = null;

    public function __construct(FerienRepositoryInterface $ferienRepository)
    {
        $this->ferienRepository = $ferienRepository;
    }

    /** @return array<string,string> Y-m-d → Name */
    private function getFreieTage(): array
    {
        if ($this->freieTage === null) {
            $this->freieTage = [];
            foreach ($this->ferienRepository->getAll() as $ferien) {
                $tag = clone $ferien->getStartdatum();
                while ($tag <= $ferien->getEnddatum()) {
                    $this->freieTage[$tag->format('Y-m-d')] = $ferien->getName();
                    $tag->modify('+1 day');
                }
            }
        }
        return $this->freieTage;
    }

    public function isWochenende(DateTime $tag): bool
    {
        return (int)$tag->format('N') >= 6;
    }

    /** Name der Ferien/des Feiertags an diesem Tag oder null. */
    public function getFerienName(DateTime $tag): ?string
    {
        return $this->getFreieTage()[$tag->format('Y-m-d')] ?? null;
    }

    public function isSchultag(DateTime $tag): bool
    {
        return !$this->isWochenende($tag) && $this->getFerienName($tag) === null;
    }

    /** Begründung, warum der Tag kein Schultag ist (für Meldungen), sonst null. */
    public function getGrundKeinSchultag(DateTime $tag): ?string
    {
        $ferien = $this->getFerienName($tag);
        if ($ferien !== null) return $ferien;
        if ($this->isWochenende($tag)) return 'Wochenende';
        return null;
    }

    /** Der Tag selbst, falls Schultag, sonst der nächste Schultag danach. */
    public function naechsterSchultag(DateTime $tag): DateTime
    {
        $kandidat = clone $tag;
        // Obergrenze verhindert Endlosschleifen bei fehlerhaften Ferien-Daten (z. B. ganzjährig)
        for ($i = 0; $i < 120 && !$this->isSchultag($kandidat); $i++) {
            $kandidat->modify('+1 day');
        }
        return $kandidat;
    }

    /**
     * Geht $anzahl Schultage vor (positiv) oder zurück (negativ).
     * Bei 0 wird der Tag selbst geliefert.
     */
    public function verschiebeUmSchultage(DateTime $tag, int $anzahl): DateTime
    {
        $ergebnis = clone $tag;
        $schritt = $anzahl >= 0 ? '+1 day' : '-1 day';
        $rest = abs($anzahl);
        for ($i = 0; $rest > 0 && $i < 400; $i++) {
            $ergebnis->modify($schritt);
            if ($this->isSchultag($ergebnis)) {
                $rest--;
            }
        }
        return $ergebnis;
    }

    /**
     * Anzahl Schultage im Intervall (von, bis] – also wie viele Schultage man von
     * $von aus vorgehen muss, um $bis zu erreichen. Negativ, wenn $bis vor $von liegt.
     */
    public function schultageZwischen(DateTime $von, DateTime $bis): int
    {
        if ($bis < $von) {
            return -$this->schultageZwischen($bis, $von);
        }
        $anzahl = 0;
        $tag = clone $von;
        for ($i = 0; $tag < $bis && $i < 1000; $i++) {
            $tag->modify('+1 day');
            if ($this->isSchultag($tag)) {
                $anzahl++;
            }
        }
        return $anzahl;
    }

    /** Gibt es im Zeitraum überhaupt hinterlegte Ferien/Feiertage? */
    public function hatFerienDaten(DateTime $von, DateTime $bis): bool
    {
        $vonStr = $von->format('Y-m-d');
        $bisStr = $bis->format('Y-m-d');
        foreach (array_keys($this->getFreieTage()) as $tag) {
            if ($tag >= $vonStr && $tag <= $bisStr) return true;
        }
        return false;
    }
}
