<?php
declare(strict_types=1);

namespace MH\Timetable\Controller;

use MH\Timetable\Model\Entity\Timetable;
use MH\Timetable\Model\Repository\TimetableRepositoryInterface;
use MH\Timetable\Model\Repository\TermineRepositoryInterface;
use MH\Timetable\Service\TimetableCopyService;
use MH\Timetable\Install\Schema;
use DateTime;

/**
 * Controller für die Verwaltung von Zeittafeln (Timetables).
 */
class TimetableController
{
    private TimetableRepositoryInterface $repository;
    private TermineRepositoryInterface $termineRepository;
    private TimetableCopyService $copyService;

    public function __construct(
        TimetableRepositoryInterface $repository,
        TermineRepositoryInterface $termineRepository,
        TimetableCopyService $copyService
    ) {
        $this->repository = $repository;
        $this->termineRepository = $termineRepository;
        $this->copyService = $copyService;
    }

    /**
     * Verarbeitet das Speichern oder Aktualisieren einer Zeittafel.
     */
    public function processFormSubmission(array $formData): array
    {
        if (!isset($formData['timetable_speichern_nonce']) || 
            !wp_verify_nonce($formData['timetable_speichern_nonce'], 'timetable_speichern_nonce')) {
            wp_die('Sicherheitsprüfung fehlgeschlagen.');
        }

        $sanitizedData = [
            'id'           => !empty($formData['id']) ? (int)$formData['id'] : null,
            'bezeichnung'  => sanitize_text_field($formData['bezeichnung'] ?? ''),
            'beschreibung' => sanitize_text_field($formData['beschreibung'] ?? ''),
            'status'       => sanitize_key($formData['status'] ?? Timetable::STATUS_VEROEFFENTLICHT),
        ];

        $errors = $this->checkFormData($sanitizedData);

        if (empty($errors)) {
            $ok = $sanitizedData['id'] === null
                ? $this->addObject($sanitizedData)
                : $this->editObject($sanitizedData['id'], $sanitizedData);
            if (!$ok) {
                $errors['datenbank'][] = 'Die Timetable konnte nicht gespeichert werden (Datenbankfehler).';
            }
        }

        return $errors;
    }

    public function addObject(array $data): bool
    {
        $timetable = new Timetable();
        $timetable->setBezeichnung($data['bezeichnung']);
        $timetable->setBeschreibung($data['beschreibung']);
        $timetable->setStatus($data['status'] ?? Timetable::STATUS_VEROEFFENTLICHT);

        return $this->repository->create($timetable);
    }

    public function editObject(int $id, array $data): bool
    {
        $timetable = $this->repository->find($id);
        if (!$timetable) {
            return false;
        }

        $timetable->setBezeichnung($data['bezeichnung']);
        $timetable->setBeschreibung($data['beschreibung']);
        if (isset($data['status'])) {
            $timetable->setStatus($data['status']);
        }

        return $this->repository->update($id, $timetable);
    }

    /**
     * Löscht eine Zeittafel, sofern keine Termine mehr zugeordnet sind.
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteObject(int $id): array
    {
        $timetable = $this->repository->find($id);
        
        if (!$timetable) {
            return ['success' => false, 'message' => 'Zeittafel nicht gefunden.'];
        }

        $anzahlTermine = $this->termineRepository->countByTimetableId($id);
        if ($anzahlTermine > 0) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Die Timetable „%s“ kann nicht gelöscht werden, da ihr noch %d Termin(e) zugeordnet sind.',
                    $timetable->getBezeichnung(),
                    $anzahlTermine
                )
            ];
        }

        $deleted = $this->repository->delete($id);
        return [
            'success' => $deleted,
            'message' => $deleted ? 'Erfolgreich gelöscht.' : 'Fehler beim Löschen.'
        ];
    }

    /**
     * Bereitet Daten für ein Dropdown-Menü auf.
     */
    public function getTimetablesForDropdown(): array
    {
        $all = $this->repository->getAll();
        $dropdown = [];

        foreach ($all as $timetable) {
            $dropdown[] = [
                'id'          => $timetable->getId(),
                'bezeichnung' => $timetable->getBezeichnung()
            ];
        }

        return $dropdown;
    }

    private function checkFormData(array $data): array
    {
        $errors = [];
        if (empty($data['bezeichnung'])) {
            $errors['bezeichnung'][] = 'Bitte gib eine Bezeichnung an.';
        }
        if (empty($data['beschreibung'])) {
            $errors['beschreibung'][] = 'Bitte gib eine Beschreibung an.';
        }
        if (mb_strlen($data['bezeichnung']) > Schema::LEN_TIMETABLE_BEZEICHNUNG) {
            $errors['bezeichnung'][] = sprintf('Die Bezeichnung ist zu lang (höchstens %d Zeichen).', Schema::LEN_TIMETABLE_BEZEICHNUNG);
        }
        if (mb_strlen($data['beschreibung']) > Schema::LEN_TIMETABLE_BESCHREIBUNG) {
            $errors['beschreibung'][] = sprintf('Die Beschreibung ist zu lang (höchstens %d Zeichen).', Schema::LEN_TIMETABLE_BESCHREIBUNG);
        }
        return $errors;
    }

    public function getObjectById(int $id): ?Timetable
    {
        return $this->repository->find($id);
    }
	

		public function getAllTimetables(): array
		{
			return $this->repository->getAll();
		}

		public function getTimetableDataAjax(): void
		{
			// 1. Sicherheit: Nonce und Berechtigung prüfen
			if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'mh_tt_nonce')
				|| !current_user_can('manage_options')) {
				wp_send_json_error(['message' => 'Sicherheits-Check fehlgeschlagen.'], 403);
			}

			// 2. ID holen
			$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

			// 3. Über das Repository suchen
			$tt = $this->repository->find($id);

			if (!$tt) {
				wp_send_json_error(['message' => 'Zeittafel nicht gefunden.']);
			}

			// 4. Daten als JSON zurückgeben (Mapping auf die JS-Felder)
			wp_send_json_success([
				'id'           => $tt->getId(),
				'bezeichnung'  => $tt->getBezeichnung(),
				'beschreibung' => $tt->getBeschreibung(),
				'status'       => $tt->getStatus(),
			]);

			// wp_send_json_success ruft automatisch die() auf, 
			// das verhindert, dass WordPress eine '0' anhängt.
		}
		
		/**
		 * Kopiert eine Timetable (siehe TimetableCopyService::duplicate für das Ergebnis-Array).
		 */
		public function copyTimetable(array $data): array
		{
			$sourceId = (int)($data['source_id'] ?? 0);
			$newName = sanitize_text_field($data['new_name'] ?? '');
			$newEndDate = DateTime::createFromFormat('!Y-m-d', (string)($data['new_end_date'] ?? ''));

			if ($sourceId <= 0 || $newName === '' || !$newEndDate) {
				return ['success' => false, 'message' => 'Bitte Namen und ein gültiges Enddatum angeben.', 'newId' => null, 'anzahl' => 0, 'verschoben' => [], 'warnungen' => []];
			}
			if (mb_strlen($newName) > Schema::LEN_TIMETABLE_BEZEICHNUNG) {
				return ['success' => false, 'message' => sprintf('Der Name ist zu lang (höchstens %d Zeichen).', Schema::LEN_TIMETABLE_BEZEICHNUNG), 'newId' => null, 'anzahl' => 0, 'verschoben' => [], 'warnungen' => []];
			}

			return $this->copyService->duplicate(
				$sourceId,
				$newName,
				$newEndDate,
				($data['verschiebung'] ?? 'wochen') !== 'tage',
				!empty($data['schultage'])
			);
		}
}