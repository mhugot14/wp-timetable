<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

/**
 * Service zur Kommunikation mit externen Ferien-APIs.
 */
class HolidayApiService
{
    public function fetchFerien(int $jahr, string $bundesland): array
    {
        $url = sprintf('https://ferien-api.de/api/v1/holidays/%s/%d', rawurlencode($bundesland), $jahr);
        return $this->getJson($url);
    }

    public function fetchFeiertage(int $jahr, string $bundesland): array
    {
        $url = add_query_arg(['jahr' => $jahr, 'nur_land' => $bundesland], 'https://feiertage-api.de/api/');
        return $this->getJson($url);
    }

    private function getJson(string $url): array
    {
        $response = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log('MH Timetable: API-Abruf fehlgeschlagen: ' . $url);
            return [];
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : [];
    }
}