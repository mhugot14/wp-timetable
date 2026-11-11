<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

use MH\Timetable\Model\Entity\Termin;
use MH\Timetable\Model\Repository\TermineRepositoryInterface;
use DateTime;

/**
 * Sammelt Hinweise zu Terminen. Hinweise blockieren das Speichern nicht.
 *
 *  - Beginn oder Ende an einem Wochenende, in den Ferien oder an einem Feiertag
 *  - Überschneidung mit einem Termin ANDEREN Typs im selben Bildungsgang
 *    (mehrere Konferenzen gleichen Typs am selben Tag sind normal, z. B. je Klasse)
 *  - mögliche Dublette (gleicher Typ, gleiche Bezeichnung, gleiche Daten)
 *  - Verstöße gegen abhängige Fristen (FristenService)
 */
class KonfliktService
{
    private SchulkalenderService $kalender;
    private FristenService $fristen;
    private TermineRepositoryInterface $termineRepo;

    public function __construct(SchulkalenderService $kalender, FristenService $fristen, TermineRepositoryInterface $termineRepo)
    {
        $this->kalender = $kalender;
        $this->fristen = $fristen;
        $this->termineRepo = $termineRepo;
    }

    /**
     * @param Termin[]|null $alle Termine derselben Timetable (wird sonst geladen)
     * @return string[]
     */
    public function pruefe(Termin $termin, ?array $alle = null): array
    {
        $alle ??= $this->termineRepo->getByTimetableId($termin->getTimetableId());
        $hinweise = [];

        // 1. Kein Schultag
        $tage = ['Beginn' => $termin->getBeginn()];
        if ($termin->getEnde()->format('Y-m-d') !== $termin->getBeginn()->format('Y-m-d')) {
            $tage['Ende'] = $termin->getEnde();
        }
        foreach ($tage as $label => $tag) {
            $grund = $this->kalender->getGrundKeinSchultag($tag);
            if ($grund !== null) {
                $hinweise[] = sprintf('%s am %s %s liegt auf keinem Schultag (%s).', $label, $this->wochentag($tag), $tag->format('d.m.Y'), $grund);
            }
        }

        // 2. Überschneidungen und Dubletten im selben Bildungsgang
        foreach ($alle as $anderer) {
            if ($anderer->getId() === $termin->getId() || $anderer->getBildungsgang() !== $termin->getBildungsgang()) {
                continue;
            }
            $ueberschneidung = $termin->getBeginn() <= $anderer->getEnde() && $anderer->getBeginn() <= $termin->getEnde();
            if (!$ueberschneidung) continue;

            if ($anderer->getEreignistyp() !== $termin->getEreignistyp()) {
                $hinweise[] = sprintf(
                    'Überschneidet sich mit %s „%s“ (%s–%s).',
                    $anderer->getEreignistyp(), $anderer->getBezeichnung(),
                    $anderer->getBeginn()->format('d.m.'), $anderer->getEnde()->format('d.m.Y')
                );
            } elseif ($anderer->getBezeichnung() === $termin->getBezeichnung()
                && $anderer->getBeginn() == $termin->getBeginn()
                && $anderer->getEnde() == $termin->getEnde()) {
                $hinweise[] = sprintf('Mögliche Dublette von Termin #%d (gleicher Typ, gleiche Bezeichnung, gleiche Daten).', (int)$anderer->getId());
            }
        }

        // 3. Fristen
        return array_merge($hinweise, $this->fristen->pruefe($termin, $alle));
    }

    /**
     * Hinweise für viele Termine auf einmal (lädt jede Timetable nur einmal).
     * @param Termin[] $termine
     * @return array<int, string[]> Termin-ID → Hinweise (nur Termine mit Hinweisen)
     */
    public function pruefeListe(array $termine): array
    {
        $cache = [];
        $ergebnis = [];
        foreach ($termine as $termin) {
            $ttId = $termin->getTimetableId();
            $cache[$ttId] ??= $this->termineRepo->getByTimetableId($ttId);
            $hinweise = $this->pruefe($termin, $cache[$ttId]);
            if ($hinweise) {
                $ergebnis[(int)$termin->getId()] = $hinweise;
            }
        }
        return $ergebnis;
    }

    private function wochentag(DateTime $tag): string
    {
        return ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][(int)$tag->format('N')];
    }
}
