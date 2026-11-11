<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

use MH\Timetable\Model\Entity\Timetable;
use MH\Timetable\Model\Entity\Termin;
use MH\Timetable\Model\Repository\TimetableRepositoryInterface;
use MH\Timetable\Model\Repository\TermineRepositoryInterface;
use DateTime;

/**
 * Kopiert eine Timetable samt Terminen und verschiebt sie zeitlich.
 *
 * - Wochentagsgenau: Die Verschiebung wird auf ganze Wochen gerundet, damit
 *   eine Konferenz am Dienstag auch in der Kopie an einem Dienstag liegt.
 * - Schultage beachten: Beginnt ein Termin danach in den Ferien, an einem
 *   Feiertag oder am Wochenende, wird er (mit gleicher Dauer) auf den nächsten
 *   Schultag gelegt.
 *
 * Die Kopie wird immer als Entwurf angelegt.
 */
class TimetableCopyService
{
    private TimetableRepositoryInterface $timetableRepo;
    private TermineRepositoryInterface $termineRepo;
    private SchulkalenderService $schulkalender;

    public function __construct(
        TimetableRepositoryInterface $timetableRepo,
        TermineRepositoryInterface $termineRepo,
        SchulkalenderService $schulkalender
    ) {
        $this->timetableRepo = $timetableRepo;
        $this->termineRepo = $termineRepo;
        $this->schulkalender = $schulkalender;
    }

    /**
     * @return array{
     *   success: bool, message: string, newId: ?int, anzahl: int,
     *   verschoben: string[], warnungen: string[]
     * }
     */
    public function duplicate(
        int $sourceId,
        string $newName,
        DateTime $newEndDate,
        bool $wochentagsgenau = true,
        bool $schultageBeachten = true
    ): array {
        $result = ['success' => false, 'message' => '', 'newId' => null, 'anzahl' => 0, 'verschoben' => [], 'warnungen' => []];

        // 1. Quelldaten laden
        $sourceTimetable = $this->timetableRepo->find($sourceId);
        if (!$sourceTimetable) {
            $result['message'] = 'Die Vorlage wurde nicht gefunden.';
            return $result;
        }

        $sourceTermine = $this->termineRepo->getByTimetableId($sourceId);
        $sourceTimetable->setTermine($sourceTermine);

        $oldEndDate = $sourceTimetable->getLastDate();
        if (!$oldEndDate) {
            $result['message'] = 'Die Vorlage enthält keine Termine.';
            return $result;
        }

        // 2. Verschiebung in Tagen berechnen
        $dayOffset = $this->berechneVerschiebung($oldEndDate, $newEndDate, $wochentagsgenau);

        // 3. Neue Timetable (immer als Entwurf) anlegen
        $newTimetable = new Timetable();
        $newTimetable->setBezeichnung($newName);
        $newTimetable->setBeschreibung('Kopie von ' . $sourceTimetable->getBezeichnung());
        $newTimetable->setStatus(Timetable::STATUS_ENTWURF);

        if (!$this->timetableRepo->create($newTimetable) || !$newTimetable->getId()) {
            $result['message'] = 'Die neue Timetable konnte nicht angelegt werden.';
            return $result;
        }
        $newId = (int)$newTimetable->getId();

        // 4. Termine kopieren und verschieben
        $neuVon = null;
        $neuBis = null;
        foreach ($sourceTermine as $oldTermin) {
            $newStart = (clone $oldTermin->getBeginn())->modify("$dayOffset days");
            $newEnd   = (clone $oldTermin->getEnde())->modify("$dayOffset days");

            // Zielzeitraum VOR dem Schultag-Ausgleich merken – nur so lässt sich
            // prüfen, ob für diesen Zeitraum überhaupt Ferien hinterlegt sind.
            $neuVon = ($neuVon === null || $newStart < $neuVon) ? clone $newStart : $neuVon;
            $neuBis = ($neuBis === null || $newEnd > $neuBis) ? clone $newEnd : $neuBis;

            if ($schultageBeachten && !$this->schulkalender->isSchultag($newStart)) {
                $grund = $this->schulkalender->getGrundKeinSchultag($newStart);
                $ziel = $this->schulkalender->naechsterSchultag($newStart);
                $tage = (int)$newStart->diff($ziel)->days;
                $newEnd->modify("+$tage days");
                $result['verschoben'][] = sprintf(
                    '%s – %s: %s → %s (%s)',
                    $oldTermin->getBildungsgang(),
                    $oldTermin->getBezeichnung(),
                    $newStart->format('d.m.Y'),
                    $ziel->format('d.m.Y'),
                    $grund
                );
                $newStart = $ziel;
            }

            $newTermin = new Termin(
                $newStart,
                $newEnd,
                $oldTermin->getBildungsgang(),
                $oldTermin->getBezeichnung(),
                $oldTermin->getEreignistyp(),
                $oldTermin->getVerantwortlich(),
                $newId
            );

            if ($this->termineRepo->create($newTermin)) {
                $result['anzahl']++;
            }
        }

        // 5. Hinweise
        if ($schultageBeachten && $neuVon && !$this->schulkalender->hatFerienDaten($neuVon, $neuBis)) {
            $result['warnungen'][] = sprintf(
                'Für den Zeitraum %s – %s sind keine Ferien oder Feiertage hinterlegt. Es wurden nur Wochenenden berücksichtigt. Ferien bitte unter „Einstellungen“ importieren.',
                $neuVon->format('d.m.Y'),
                $neuBis->format('d.m.Y')
            );
        }
        if ($wochentagsgenau && $dayOffset % 7 === 0) {
            $tatsaechlichesEnde = (clone $oldEndDate)->modify("$dayOffset days");
            if ($tatsaechlichesEnde->format('Y-m-d') !== $newEndDate->format('Y-m-d')) {
                $result['warnungen'][] = sprintf(
                    'Wochentagsgenau verschoben: Der letzte Termin endet am %s statt am gewünschten %s.',
                    $tatsaechlichesEnde->format('d.m.Y'),
                    $newEndDate->format('d.m.Y')
                );
            }
        }

        $result['success'] = true;
        $result['newId'] = $newId;
        $result['message'] = sprintf('„%s“ wurde als Entwurf mit %d Termin(en) angelegt.', $newName, $result['anzahl']);
        return $result;
    }

    /** Verschiebung in Tagen; wochentagsgenau auf ganze Wochen gerundet. */
    private function berechneVerschiebung(DateTime $altesEnde, DateTime $neuesEnde, bool $wochentagsgenau): int
    {
        $tage = (int)$altesEnde->diff($neuesEnde)->days;
        if ($neuesEnde < $altesEnde) {
            $tage *= -1;
        }
        if ($wochentagsgenau) {
            $tage = (int)round($tage / 7) * 7;
        }
        return $tage;
    }
}
