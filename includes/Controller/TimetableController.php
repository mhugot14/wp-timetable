<?php
declare(strict_types=1);

namespace MH\Timetable\Controller;

use MH\Timetable\Model\Entity\Timetable;
use MH\Timetable\Model\Repository\TimetableRepositoryInterface;

/**
 * Controller für die Verwaltung von Zeittafeln (Timetables).
 */
class TimetableController
{
    private TimetableRepositoryInterface $repository;

    public function __construct(TimetableRepositoryInterface $repository)
    {
        $this->repository = $repository;
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
        ];

        $errors = $this->checkFormData($sanitizedData);

        if (empty($errors)) {
            if ($sanitizedData['id'] === null) {
                $this->addObject($sanitizedData);
            } else {
                $this->editObject($sanitizedData['id'], $sanitizedData);
            }
        }

        return $errors;
    }

    public function addObject(array $data): bool
    {
        $timetable = new Timetable();
        $timetable->setBezeichnung($data['bezeichnung']);
        $timetable->setBeschreibung($data['beschreibung']);

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

        // Hinweis: Die Prüfung auf zugeordnete Termine sollte idealerweise 
        // über das TermineRepository erfolgen. 
        // Hier implementieren wir die Logik analog zu deinem alten Stand:
        if ($timetable->getLaengeInTagen() > 0) {
            return [
                'success' => false, 
                'message' => 'Timetable kann nicht gelöscht werden, da noch Termine zugeordnet sind.'
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
			// 1. Sicherheit: Nonce prüfen
			if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'mh_tt_nonce')) {
				wp_send_json_error(['message' => 'Sicherheits-Check fehlgeschlagen.']);
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
				'beschreibung' => $tt->getBeschreibung()
			]);

			// wp_send_json_success ruft automatisch die() auf, 
			// das verhindert, dass WordPress eine '0' anhängt.
		}
}