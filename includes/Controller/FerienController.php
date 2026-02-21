<?php
declare(strict_types=1);

namespace MH\Timetable\Controller;

use MH\Timetable\Model\Entity\Ferien;
use MH\Timetable\Model\Repository\FerienRepositoryInterface;
use MH\Timetable\Service\HolidayApiService;
use DateTime;
use Exception;

/**
 * Controller für die Ferien-Verwaltung.
 * Verbindet Repository, API-Service und die View.
 */
class FerienController
{
    private FerienRepositoryInterface $repository;
    private HolidayApiService $apiService;

    public function __construct(
        FerienRepositoryInterface $repository,
        HolidayApiService $apiService
    ) {
        $this->repository = $repository;
        $this->apiService = $apiService;
    }

    /**
     * Holt alle Ferien-Entities aus dem Repository.
     * @return Ferien[]
     */
    public function getAllFerien(): array
    {
        return $this->repository->getAll();
    }

    /**
     * Speichert einen manuell eingegebenen Ferientag.
     */
    public function saveFerien(array $data): bool
    {
        try {
            $ferien = new Ferien(
                sanitize_text_field($data['name']),
                new DateTime($data['startdatum']),
                new DateTime($data['enddatum']),
                sanitize_text_field($data['typ'])
            );

            return $this->repository->create($ferien);
        } catch (Exception $e) {
            error_log('Fehler beim Speichern der Ferien: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Löscht einen Ferientag über das Repository.
     */
    public function deleteFerien(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Startet den Import von Ferien und Feiertagen über die API-Services.
     * @return int Anzahl der neu importierten Einträge.
     */
    public function importFromApi(int $jahr, string $bundesland): int
    {
        $importCount = 0;

        // 1. Ferien abrufen
        $ferienRaw = $this->apiService->fetchFerien($jahr, $bundesland);
        foreach ($ferienRaw as $item) {
            try {
                $ferien = new Ferien(
                    (string)$item['name'],
                    new DateTime($item['start']),
                    new DateTime($item['end']),
                    'Ferien'
                );
                if ($this->repository->create($ferien)) {
                    $importCount++;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        // 2. Feiertage abrufen
        $feiertageRaw = $this->apiService->fetchFeiertage($jahr, $bundesland);
        foreach ($feiertageRaw as $name => $item) {
            try {
                $feiertag = new Ferien(
                    (string)$name,
                    new DateTime($item['datum']),
                    new DateTime($item['datum']),
                    'Feiertag'
                );
                if ($this->repository->create($feiertag)) {
                    $importCount++;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $importCount;
    }
}