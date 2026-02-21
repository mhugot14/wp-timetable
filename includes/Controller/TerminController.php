<?php
declare(strict_types=1);

namespace MH\Timetable\Controller;

use MH\Timetable\Model\Entity\Termin;
use MH\Timetable\Model\Repository\TermineRepositoryInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use DateTime;

/**
 * Controller für die Verwaltung von Terminen.
 * Steuert den Datenfluss zwischen View, Entity und Repository.
 */
class TerminController
{
    private TermineRepositoryInterface $repository;

    public function __construct(TermineRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Verarbeitet das Speichern eines Termins aus einem Admin-Formular.
     */
    public function processFormSubmission(array $formData): array
    {
        if (!isset($formData['termin_speichern_nonce']) || 
            !wp_verify_nonce($formData['termin_speichern_nonce'], 'termin_speichern_nonce')) {
            wp_die('Sicherheitsprüfung fehlgeschlagen (Nonce).');
        }

        $sanitizedData = $this->sanitizeFormData($formData);
        $errors = $this->checkFormData($sanitizedData);

        if (empty($errors)) {
            if (empty($formData['id'])) {
                $this->addObject($sanitizedData);
            } else {
                $this->editObject((int)$formData['id'], $sanitizedData);
            }
        }

        return $errors;
    }

    /**
     * Bereinigt die Eingabedaten.
     */
    private function sanitizeFormData(array $data): array
    {
        return [
            'timetableId'    => (int)($data['timetable_ID'] ?? 0),
            'bezeichnung'    => sanitize_text_field($data['bezeichnung'] ?? ''),
            'bildungsgang'   => sanitize_text_field($data['bildungsgang'] ?? ''),
            'beginn'         => sanitize_text_field($data['beginn'] ?? ''),
            'ende'           => sanitize_text_field($data['ende'] ?? ''),
            'verantwortlich' => sanitize_text_field($data['verantwortlich'] ?? ''),
            'ereignistyp'    => sanitize_text_field($data['ereignistyp'] ?? ''),
        ];
    }

    public function addObject(array $data): bool
    {
        $termin = new Termin(
            new DateTime($data['beginn']),
            new DateTime($data['ende']),
            $data['bildungsgang'],
            $data['bezeichnung'],
            $data['ereignistyp'],
            $data['verantwortlich'],
            $data['timetableId']
        );

        return $this->repository->create($termin);
    }

    public function editObject(int $id, array $data): bool
    {
        $termin = new Termin(
            new DateTime($data['beginn']),
            new DateTime($data['ende']),
            $data['bildungsgang'],
            $data['bezeichnung'],
            $data['ereignistyp'],
            $data['verantwortlich'],
            $data['timetableId']
        );
        $termin->setId($id);

        return $this->repository->update($id, $termin);
    }

    public function getObjectById(int $id): ?Termin
    {
        return $this->repository->find($id);
    }

    public function deleteObject(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Validiert die Daten vor dem Speichern.
     */
    private function checkFormData(array $data): array
    {
        $errors = [];
        if ($data['timetableId'] <= 0) {
            $errors['timetable_ID'][] = 'Bitte wählen Sie eine Zeittafel aus.';
        }
        if (empty($data['bezeichnung'])) {
            $errors['bezeichnung'][] = 'Bezeichnung fehlt.';
        }
        if (empty($data['beginn']) || empty($data['ende'])) {
            $errors['datum'][] = 'Zeitraum unvollständig.';
        } else {
            $start = new DateTime($data['beginn']);
            $end = new DateTime($data['ende']);
            if ($end < $start) {
                $errors['ende'][] = 'Das Ende darf nicht vor dem Beginn liegen.';
            }
        }
        return $errors;
    }

    /**
     * Verarbeitet Massenänderungen (Bulk Edit).
     */
    public function processBulkEdit(array $ids, array $formData): void
    {
        // Mapping der Formularfelder auf Entity-Setter
        $fieldToMethod = [
            'timetable_ID' => 'setTimetableId',
            'bezeichnung'  => 'setBezeichnung',
            'bildungsgang' => 'setBildungsgang',
            'beginn'       => 'setBeginn',
            'ende'         => 'setEnde',
            'verantwortlich' => 'setVerantwortlich',
            'ereignistyp'  => 'setEreignistyp',
        ];

        foreach ($ids as $id) {
            $termin = $this->getObjectById((int)$id);
            if (!$termin) continue;

            foreach ($formData as $field => $value) {
                if (!empty($value) && isset($fieldToMethod[$field])) {
                    $method = $fieldToMethod[$field];
                    
                    // Datumsfelder benötigen DateTime-Objekte
                    if ($field === 'beginn' || $field === 'ende') {
                        $value = new DateTime($value);
                    }
                    
                    if (method_exists($termin, $method)) {
                        $termin->$method($value);
                    }
                }
            }
            $this->repository->update((int)$id, $termin);
        }
    }

    /**
     * CSV-Import Logik
     */
    public function handleCsvUpload(): string
    {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            return "Fehler beim Datei-Upload.";
        }

        $filePath = $_FILES['csv_file']['tmp_name'];
        return $this->readCsv($filePath);
    }

    private function readCsv(string $filePath): string
    {
        $reader = IOFactory::createReader('Csv');
        $reader->setDelimiter(';');
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        
        $count = 0;
        $headers = [];
        $isFirstRow = true;

        foreach ($worksheet->getRowIterator() as $row) {
            if ($isFirstRow) {
                foreach ($row->getCellIterator() as $cell) {
                    $headers[] = $cell->getFormattedValue();
                }
                $isFirstRow = false;
                continue;
            }

            $rowData = [];
            $i = 0;
            foreach ($row->getCellIterator() as $cell) {
                $rowData[$headers[$i] ?? $i] = $cell->getFormattedValue();
                $i++;
            }

            // Import via addObject
            $this->addObject([
                'beginn'         => $rowData['beginn'] ?? '',
                'ende'           => $rowData['ende'] ?? '',
                'bildungsgang'   => $rowData['bildungsgang'] ?? '',
                'bezeichnung'    => $rowData['bezeichnung'] ?? '',
                'ereignistyp'    => $rowData['ereignistyp'] ?? '',
                'verantwortlich' => $rowData['verantwortlich'] ?? '',
                'timetableId'    => (int)($rowData['timetable_ID'] ?? 0)
            ]);
            $count++;
        }

        return "Erfolgreich $count Termine importiert.";
    }
	
	/**
 * Holt gefilterte Termine vom Repository.
 * @return \MH\Timetable\Model\Entity\Termin[]
 */
	public function getFilteredTermine(?int $timetableId, ?string $bildungsgang, ?string $ereignistyp): array
	{
		// Wir rufen die getFiltered Methode des Repositories auf
		return $this->repository->getFiltered($timetableId, $bildungsgang, $ereignistyp);
	}
	
	public function getTerminDataAjax(): void
	{
		 // Sicherheit: Nonce prüfen
		if (!check_ajax_referer('mh_tt_nonce', 'nonce', false)) {
			wp_send_json_error(['message' => 'Sicherheits-Check fehlgeschlagen.']);
		}

		$id = (int)($_GET['id'] ?? 0);
		$termin = $this->repository->find($id);

		if (!$termin) {
			wp_send_json_error(['message' => 'Termin nicht gefunden.']);
		}

		wp_send_json_success([
			'id'             => $termin->getId(),
			'bezeichnung'    => $termin->getBezeichnung(),
			'bildungsgang'   => $termin->getBildungsgang(),
			'ereignistyp'    => $termin->getEreignistyp(),
			'beginn'         => $termin->getBeginn()->format('Y-m-d'),
			'ende'           => $termin->getEnde()->format('Y-m-d'),
			'verantwortlich' => $termin->getVerantwortlich(),
			'timetableId'    => $termin->getTimetableId(),
		]);
	}
	
	public function processBulkSubmission(array $formData): void
	{
		$ids = explode(',', $formData['id']);

		// Wir sammeln nur die Daten, die NICHT leer sind
		$updateData = [];
		if (!empty($formData['timetable_ID'])) $updateData['timetableId'] = (int)$formData['timetable_ID'];
		if (!empty($formData['bildungsgang']))  $updateData['bildungsgang'] = sanitize_text_field($formData['bildungsgang']);
		  if (!empty($formData['bezeichnung']))   $updateData['bezeichnung'] = sanitize_text_field($formData['bezeichnung']);
		if (!empty($formData['ereignistyp']))   $updateData['ereignistyp'] = sanitize_text_field($formData['ereignistyp']);
		if (!empty($formData['beginn']))        $updateData['beginn'] = new \DateTime($formData['beginn']);
		if (!empty($formData['ende']))          $updateData['ende'] = new \DateTime($formData['ende']);
		if (!empty($formData['verantwortlich'])) $updateData['verantwortlich'] = sanitize_text_field($formData['verantwortlich']);

		if (empty($updateData)) return;

		foreach ($ids as $id) {
			$termin = $this->repository->find((int)$id);
			if (!$termin) continue;

			// Nur die geänderten Felder in die Entity schreiben
			if (isset($updateData['timetableId']))   $termin->setTimetableId($updateData['timetableId']);
			if (isset($updateData['bildungsgang']))  $termin->setBildungsgang($updateData['bildungsgang']);
			   if (isset($updateData['bezeichnung']))   $termin->setBezeichnung($updateData['bezeichnung']);
			if (isset($updateData['ereignistyp']))   $termin->setEreignistyp($updateData['ereignistyp']);
			if (isset($updateData['beginn']))        $termin->setBeginn($updateData['beginn']);
			if (isset($updateData['ende']))          $termin->setEnde($updateData['ende']);
			if (isset($updateData['verantwortlich'])) $termin->setVerantwortlich($updateData['verantwortlich']);

			$this->repository->update((int)$id, $termin);
		}
	}
}