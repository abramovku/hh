<?php

namespace App\Services\Location;

use App\Traits\GeneratesRid;
use App\Traits\SanitizesPhone;
use Illuminate\Support\Facades\Log;

/**
 * Region (Estaff location_id) lookup by phone number.
 *
 * Public methods never throw: any failure is logged to the `location`
 * channel and an empty result is returned so candidate processing continues.
 */
class Location
{
    use GeneratesRid, SanitizesPhone;

    private $config;

    private $client;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new LocationClient($config);
    }

    /**
     * Raw API response: number, region, operator, location_id. Empty array on failure.
     */
    public function lookup(string $phone): array
    {
        $rid = $this->newRid();

        if (empty($this->config['url'])) {
            Log::channel('location')->warning(__FUNCTION__.' skipped: LOCATION_API_URL not configured', ['rid' => $rid]);

            return [];
        }

        $number = $this->normalizePhone($phone);

        if ($number === null) {
            Log::channel('location')->error(__FUNCTION__.' invalid phone', ['rid' => $rid, 'phone' => $phone]);

            return [];
        }

        Log::channel('location')->info(__FUNCTION__.' send', ['rid' => $rid, 'number' => $number]);

        try {
            $data = $this->client->get('region', ['number' => $number]);
        } catch (\Throwable $e) {
            Log::channel('location')->error(__FUNCTION__.' failed', [
                'rid' => $rid, 'number' => $number, 'message' => $e->getMessage(),
            ]);

            return [];
        }

        Log::channel('location')->info(__FUNCTION__.' get', ['rid' => $rid] + $data);

        return $data;
    }

    /**
     * Estaff location_id for the phone, or null when not matched / on error.
     */
    public function locationId(string $phone): ?string
    {
        $data = $this->lookup($phone);

        if ($data === []) {
            return null;
        }

        $locationId = $data['location_id'] ?? null;

        if (! is_string($locationId) || trim($locationId) === '') {
            Log::channel('location')->error(__FUNCTION__.' not matched', ['phone' => $phone, 'data' => $data]);

            return null;
        }

        return trim($locationId);
    }

    /**
     * 11 digits without "+", leading 8 replaced with 7. Null when the phone is not usable.
     */
    private function normalizePhone(string $phone): ?string
    {
        $number = preg_replace('/\D+/', '', $this->sanitizePhone($phone));

        if (strlen($number) === 10) {
            $number = '7'.$number;
        }

        if (strlen($number) === 11 && $number[0] === '8') {
            $number = '7'.substr($number, 1);
        }

        return strlen($number) === 11 ? $number : null;
    }
}
