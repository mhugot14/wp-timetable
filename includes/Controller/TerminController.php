<?php
declare(strict_types=1);

namespace MH\Timetable\Controller;

use MH\Timetable\Model\Entity\Termin;
use MH\Timetable\Model\Repository\TermineRepositoryInterface;
use MH\Timetable\Install\Schema;
use MH\Timetable\Service\FristenService;
use MH\Timetable\Service\KonfliktService;
use DateTime;

/**
 * Controller für die Verwaltung von Terminen.
 * Steuert den Datenfluss zwischen View, Entity und Repository.
 */
class TerminController
{
    private TermineRepositoryInterface $repository;
    private ?FristenService $fristen;
    private ?KonfliktService $konflikte;

    /** @var array{info: string[], warning: string[]} Hinweise des letzten Speichervorgangs */
    private array $hinweise = ['info' => [], 'warning' => []];

    public function __construct(
        TermineRepositoryInterface $repository,
        ?FristenService $fristen = null,
        ?KonfliktService $konflikte = null
    ) {
        $this->repository = $repository;
        $this->fristen = $fristen;
        $this->konflikte = $konflikte;
    }

    /**
     * Hinweise des letzten Speicherns: mitverschobene Termine (info) und
     * Konflikte (warning). Blockieren das Speichern nicht.
     * @return array{info: string[], warning: string[]}
     */
    public function getHinweise(): array
    {
        return $this->hinweise;
    }

    /**
     * Nach dem Speichern: abhängige Fristen mitziehen und Konflikte sammeln.
     * @param Termin|null $alt Zustand vor der Änderung (null bei neuem Termin)
     */
    private function nachSpeichern(int $id, ?Termin $alt): void
    {
        $neu = $this->repository->find($id);
        if (!$neu) return;

        if ($alt && $this->fristen) {
            $this->hinweise['info'] = array_merge($this->hinweise['info'], $this->fristen->anwenden($neu, $alt));
        }
        if ($this->konflikte) {
            foreach ($this->konflikte->pruefe($neu) as $hinweis) {
                $this->hinweise['warning'][] = sprintf('%s %s „%s“: %s', $neu->getBildungsgang(), $neu->getEreignistyp(), $neu->getBezeichnung(), $hinweis);
            }
        }
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
            $id = (int)($formData['id'] ?? 0);
            $alt = $id > 0 ? $this->repository->find($id) : null;

            if ($id > 0) {
                $ok = $this->editObject($id, $sanitizedData);
            } else {
                $id = $this->addObject($sanitizedData);
                $ok = $id > 0;
            }

            if ($ok) {
                $this->nachSpeichern($id, $alt);
            } else {
                $errors['datenbank'][] = 'Der Termin konnte nicht gespeichert werden (Datenbankfehler).';
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

    /** @return int ID des neuen Termins, 0 bei Fehler */
    public function addObject(array $data): int
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

        return $this->repository->create($termin) ? (int)$termin->getId() : 0;
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
        $errors = array_merge_recursive($errors, self::checkLengths($data));
        if (empty($data['bildungsgang'])) {
            $errors['bildungsgang'][] = 'Bitte wählen Sie einen Bildungsgang aus.';
        }
        if (empty($data['ereignistyp'])) {
            $errors['ereignistyp'][] = 'Bitte wählen Sie einen Ereignistyp aus.';
        }
        if (empty($data['beginn']) || empty($data['ende'])) {
            $errors['datum'][] = 'Zeitraum unvollständig.';
        } else {
            $start = self::parseDate($data['beginn']);
            $end = self::parseDate($data['ende']);
            if (!$start || !$end) {
                $errors['datum'][] = 'Ungültiges Datum im Zeitraum.';
            } elseif ($end < $start) {
                $errors['ende'][] = 'Das Ende darf nicht vor dem Beginn liegen.';
            }
        }
        return $errors;
    }

    /**
     * Prüft die Feldlängen gegen die Spaltenbreiten der Datenbank.
     * Nur gesetzte Felder werden geprüft (passt so auch für die Massenänderung).
     */
    private static function checkLengths(array $data): array
    {
        $limits = [
            'bezeichnung'    => ['Bezeichnung', Schema::LEN_TERMIN_BEZEICHNUNG],
            'bildungsgang'   => ['Bildungsgang', Schema::LEN_TERMIN_BILDUNGSGANG],
            'ereignistyp'    => ['Ereignistyp', Schema::LEN_TERMIN_EREIGNISTYP],
            'verantwortlich' => ['Verantwortlich', Schema::LEN_TERMIN_VERANTWORTLICH],
        ];
        $errors = [];
        foreach ($limits as $field => [$label, $max]) {
            if (isset($data[$field]) && is_string($data[$field]) && mb_strlen($data[$field]) > $max) {
                $errors[$field][] = sprintf('%s ist zu lang (höchstens %d Zeichen).', $label, $max);
            }
        }
        return $errors;
    }

    /**
     * Wandelt ein Datum im Format Y-m-d in ein DateTime (00:00 Uhr) um.
     * Liefert null bei ungültiger Eingabe statt eine Exception zu werfen.
     */
    private static function parseDate(string $value): ?DateTime
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();
        if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }
        return $date;
    }

	/**
 * Holt gefilterte Termine vom Repository.
 * @return \MH\Timetable\Model\Entity\Termin[]
 */
	public function getFilteredTermine(int $timetableId, string $bildungsgang, string $ereignistyp): array
	{
		// Wir rufen die getFiltered Methode des Repositories auf
		return $this->repository->getFiltered($timetableId, $bildungsgang, $ereignistyp);
	}
	
	public function getTerminDataAjax(): void
	{
		// Sicherheit: Nonce und Berechtigung prüfen
		if (!check_ajax_referer('mh_tt_nonce', 'nonce', false) || !current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Sicherheits-Check fehlgeschlagen.'], 403);
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
	
	/**
	 * Massenänderung: überschreibt nur die ausgefüllten Felder.
	 * @return array Fehlermeldungen (leer bei Erfolg)
	 */
	public function processBulkSubmission(array $formData): array
	{
		$ids = array_filter(array_map('intval', explode(',', (string)($formData['id'] ?? ''))));

		// Wir sammeln nur die Daten, die NICHT leer sind
		$updateData = [];
		$errors = [];
		if (!empty($formData['timetable_ID'])) $updateData['timetableId'] = (int)$formData['timetable_ID'];
		if (!empty($formData['bildungsgang']))  $updateData['bildungsgang'] = sanitize_text_field($formData['bildungsgang']);
		if (!empty($formData['bezeichnung']))   $updateData['bezeichnung'] = sanitize_text_field($formData['bezeichnung']);
		if (!empty($formData['ereignistyp']))   $updateData['ereignistyp'] = sanitize_text_field($formData['ereignistyp']);
		foreach (['beginn', 'ende'] as $field) {
			if (!empty($formData[$field])) {
				$date = self::parseDate((string)$formData[$field]);
				if ($date) {
					$updateData[$field] = $date;
				} else {
					$errors[$field][] = 'Ungültiges Datum.';
				}
			}
		}
		if (!empty($formData['verantwortlich'])) $updateData['verantwortlich'] = sanitize_text_field($formData['verantwortlich']);

		$errors = array_merge_recursive($errors, self::checkLengths($updateData));
		if (!empty($errors)) return $errors;
		if (empty($updateData)) {
			return ['allgemein' => ['Es wurde kein Feld ausgefüllt – nichts geändert.']];
		}

		foreach ($ids as $id) {
			$termin = $this->repository->find((int)$id);
			if (!$termin) continue;

			// Nur die geänderten Felder in die Entity schreiben
			if (isset($updateData['timetableId']))   $termin->setTimetableId($updateData['timetableId']);
			if (isset($updateData['bildungsgang']))  $termin->setBildungsgang($updateData['bildungsgang']);
			if (isset($updateData['bezeichnung']))   $termin->setBezeichnung($updateData['bezeichnung']);
			if (isset($updateData['ereignistyp']))   $termin->setEreignistyp($updateData['ereignistyp']);
			if (isset($updateData['beginn']))        $termin->setBeginn(clone $updateData['beginn']);
			if (isset($updateData['ende']))          $termin->setEnde(clone $updateData['ende']);
			if (isset($updateData['verantwortlich'])) $termin->setVerantwortlich($updateData['verantwortlich']);

			if ($termin->getEnde() < $termin->getBeginn()) {
				$errors['ende'][] = sprintf(
					'Termin %d („%s“) nicht geändert: Das Ende läge vor dem Beginn.',
					$id,
					$termin->getBezeichnung()
				);
				continue;
			}

			$alt = $this->repository->find((int)$id); // unveränderter Stand für die Fristen
			if ($this->repository->update((int)$id, $termin)) {
				$this->nachSpeichern((int)$id, $alt);
			}
		}

		return $errors;
	}
        /**
        * Kopiert mehrere Termine in eine Ziel-Zeittafel.
        */
       public function processBulkCopy(array $ids, int $targetTimetableId): void
       {
           foreach ($ids as $id) {
               $original = $this->repository->find((int)$id);
               if (!$original) continue;

               // Wir nutzen den PHP clone Befehl (triggert unsere __clone Methode)
               $copy = clone $original;
               $copy->setTimetableId($targetTimetableId);

               $this->repository->create($copy);
           }
       }
}