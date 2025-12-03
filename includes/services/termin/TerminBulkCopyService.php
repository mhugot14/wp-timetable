<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */



class TerminBulkCopyService
{
    private $terminRepository;
    private $timetableRepository;

    public function __construct(TerminRepository $terminRepository, TimetableRepository $timetableRepository)
    {
        $this->terminRepository   = $terminRepository;
        $this->timetableRepository = $timetableRepository;
    }

    /**
     * Kopiert mehrere Termine in eine (neue) Timetable, verschoben ab neuem Startdatum.
     *
     * @param int[] $terminIds
     * @param int   $zielTimetableId
     * @param string $neuesStartdatum  Format 'Y-m-d'
     * @return int Anzahl kopierter Termine
     * @throws Exception
     */
    public function kopiereTermineMitNeuemStartdatum(array $terminIds, $zielTimetableId, $neuesStartdatum)
    {
		//Das Startdatum ist das Startdatum der Timetable. Die Startdaten der Termine
		//werden danach dann gebaut.
        // Ziel-Timetable prüfen
        $zielTimetable = $this->timetableRepository->findeNachId($zielTimetableId);
        if (!$zielTimetable) {
            throw new Exception('Ziel-Timetable nicht gefunden.');
        }

        // Termine laden
        $termine = $this->terminRepository->findeAlleNachIds($terminIds); // musst du im Repo ergänzen
        if (empty($termine)) {
            return 0;
        }

        // Optional: sicherstellen, dass alle aus derselben Ursprungstimetable kommen
        $originalTimetableId = $termine[0]->getTimetableId();
        foreach ($termine as $termin) {
            if ($termin->getTimetableId() !== $originalTimetableId) {
                throw new Exception('Alle markierten Termine müssen aus derselben Timetable stammen.');
            }
        }

        // Basisdatum = früheste Startzeit
        $basisStart = null;
        foreach ($termine as $termin) {
            $start = new DateTime($termin->getStartzeit());
            if ($basisStart === null || $start < $basisStart) {
                $basisStart = $start;
            }
        }

        if ($basisStart === null) {
            return 0;
        }

        $neuerStart = new DateTime($neuesStartdatum);

        // Differenz in Tagen (mit Vorzeichen)
        $diffInterval = $basisStart->diff($neuerStart);
        $tageDiff     = (int) $diffInterval->format('%r%a');

        $anzahl = 0;

        foreach ($termine as $termin) {
            $start = new DateTime($termin->getStartzeit());
            $ende  = new DateTime($termin->getEndzeit());

            if ($tageDiff !== 0) {
                $start->modify(($tageDiff > 0 ? '+' : '') . $tageDiff . ' days');
                $ende->modify(($tageDiff > 0 ? '+' : '') . $tageDiff . ' days');
            }

            $kopie = clone $termin;
            $kopie->setId(null);
            $kopie->setTimetableId($zielTimetableId);
            $kopie->setStartzeit($start->format('Y-m-d H:i:s'));
            $kopie->setEndzeit($ende->format('Y-m-d H:i:s'));

            $this->terminRepository->speichere($kopie);
            $anzahl++;
        }

        return $anzahl;
    }
}


