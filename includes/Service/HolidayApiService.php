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
        $url = "https://ferien-api.de/api/v1/holidays/{$bundesland}/{$jahr}";
        $response = wp_remote_get($url);
        return json_decode(wp_remote_retrieve_body($response), true) ?: [];
    }

    public function fetchFeiertage(int $jahr, string $bundesland): array
    {
        $url = "https://feiertage-api.de/api/?jahr={$jahr}&nur_land={$bundesland}";
        $response = wp_remote_get($url);
        return json_decode(wp_remote_retrieve_body($response), true) ?: [];
    }
}