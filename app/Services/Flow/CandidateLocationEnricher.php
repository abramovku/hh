<?php

namespace App\Services\Flow;

use Illuminate\Support\Facades\Log;

/**
 * Fills Estaff `location_id` from the region-by-phone service (ТЗ 1).
 * Never throws: a missing region is logged as an error and processing continues.
 */
class CandidateLocationEnricher
{
    public function locationFor(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $locationId = app('location')->locationId($phone);

        if ($locationId === null) {
            Log::channel('location')->error('location_id not matched for phone', ['phone' => $phone]);
        }

        return $locationId;
    }

    /**
     * POST /api/twin/createCandidate payload: add candidate.location_id when absent.
     */
    public function enrichCreatePayload(array $payload): array
    {
        if (! empty($payload['candidate']['location_id'])) {
            return $payload;
        }

        $locationId = $this->locationFor($payload['candidate']['mobile_phone'] ?? null);
        if ($locationId !== null) {
            $payload['candidate']['location_id'] = $locationId;
        }

        return $payload;
    }

    /**
     * POST /api/twin/updateCandidate payload: add changed_data.location_id when absent.
     * Phone is taken from changed_data, otherwise from the candidate card.
     */
    public function enrichUpdatePayload(array $payload): array
    {
        if (! empty($payload['changed_data']['location_id'])) {
            return $payload;
        }

        $phone = $payload['changed_data']['mobile_phone'] ?? null;

        if (empty($phone) && ! empty($payload['candidate']['id'])) {
            try {
                $phone = app('estaff')->getCandidate((int) $payload['candidate']['id'])['candidate']['mobile_phone'] ?? null;
            } catch (\Throwable $e) {
                Log::channel('estaff')->error('location enrich: cannot read candidate phone', [
                    'candidate_id' => $payload['candidate']['id'], 'message' => $e->getMessage(),
                ]);
            }
        }

        $locationId = $this->locationFor($phone);
        if ($locationId !== null) {
            $payload['changed_data']['location_id'] = $locationId;
        }

        return $payload;
    }

    /**
     * ТЗ 3.2: update the region on the card before the cold call. Errors are logged, flow continues.
     */
    public function updateCandidateLocation(int $candidateId, string $phone): ?string
    {
        $locationId = $this->locationFor($phone);
        if ($locationId === null) {
            return null;
        }

        try {
            app('estaff')->updateCandidateLocation($candidateId, $locationId);
        } catch (\Throwable $e) {
            Log::channel('estaff')->error('location enrich: candidate/change failed', [
                'candidate_id' => $candidateId, 'location_id' => $locationId, 'message' => $e->getMessage(),
            ]);
        }

        return $locationId;
    }
}
