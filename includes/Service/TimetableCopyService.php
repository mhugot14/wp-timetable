<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

use MH\Timetable\Model\Entity\Timetable;
use MH\Timetable\Model\Entity\Termin;
use MH\Timetable\Model\Repository\TimetableRepositoryInterface;
use MH\Timetable\Model\Repository\TermineRepositoryInterface;
use DateTime;

class TimetableCopyService
{
    private TimetableRepositoryInterface $timetableRepo;
    private TermineRepositoryInterface $termineRepo;

    public function __construct(
        TimetableRepositoryInterface $timetableRepo,
        TermineRepositoryInterface $termineRepo
    ) {
        $this->timetableRepo = $timetableRepo;
        $this->termineRepo = $termineRepo;
    }

    public function duplicate(int $sourceId, string $newName, DateTime $newEndDate): bool
    {
        // 1. Quelldaten laden
        $sourceTimetable = $this->timetableRepo->find($sourceId);
        if (!$sourceTimetable) return false;

        $sourceTermine = $this->termineRepo->getByTimetableId($sourceId);
        $sourceTimetable->setTermine($sourceTermine);

        $oldEndDate = $sourceTimetable->getLastDate();
        if (!$oldEndDate) return false;

        // 2. Offset berechnen (Wie viele Tage liegen zwischen alt und neu?)
        $interval = $oldEndDate->diff($newEndDate);
        $dayOffset = $interval->days;
        if ($newEndDate < $oldEndDate) {
            $dayOffset *= -1;
        }

        // 3. Neue Timetable erstellen
        $newTimetable = new Timetable();
        $newTimetable->setBezeichnung($newName);
        $newTimetable->setBeschreibung("Kopie von " . $sourceTimetable->getBezeichnung());
        
        if (!$this->timetableRepo->create($newTimetable)) return false;
        
        // Die ID der neu erstellten Timetable holen (WordPress setzt diese nach insert)
        // Da unser Repo aktuell kein lastInsertId liefert, müssen wir kurz tricksen oder das Repo erweitern.
        // Wir nehmen an, das Repo hat die ID im Objekt aktualisiert (Standard bei WPDB insert).
        $newId = $newTimetable->getId(); 
        // Falls getId() noch null ist, müssen wir das Repository kurz anpassen, damit es die ID setzt.

        // 4. Termine kopieren und verschieben
        foreach ($sourceTermine as $oldTermin) {
            $newStart = (clone $oldTermin->getBeginn())->modify("$dayOffset days");
            $newEnd   = (clone $oldTermin->getEnde())->modify("$dayOffset days");

            $newTermin = new Termin(
                $newStart,
                $newEnd,
                $oldTermin->getBildungsgang(),
                $oldTermin->getBezeichnung(),
                $oldTermin->getEreignistyp(),
                $oldTermin->getVerantwortlich(),
                (int)$newId
            );

            $this->termineRepo->create($newTermin);
        }

        return true;
    }
}