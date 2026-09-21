<?php

namespace App\Services\Flow;

use App\Traits\SanitizesPhone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Duplicate and position check before any work on a candidate (ТЗ 3.1).
 *
 * 1. Load the candidate phone from Estaff.
 * 2. Find every candidate with that phone.
 * 3. Resolve position_id of each candidate's main vacancy (cached).
 * 4. Allowed when at least one position_id is in config('flow.allowed_position_ids').
 * 5. Continue with the first candidate of the Estaff answer; the rest are duplicates (logged).
 */
class CandidateGuard
{
    use SanitizesPhone;

    private const POSITION_CACHE_TTL = 3600;

    public function check(int $webhookCandidateId): GuardResult
    {
        $allowedPositions = array_map('strval', (array) config('flow.allowed_position_ids', []));

        if ($allowedPositions === []) {
            Log::channel('app')->error('flow guard: ESTAFF_ALLOWED_POSITION_IDS is empty, candidate skipped', [
                'candidate_id' => $webhookCandidateId,
            ]);

            return GuardResult::denied($webhookCandidateId, 'allowed_position_ids not configured');
        }

        $estaff = app('estaff');
        $candidate = $estaff->getCandidate($webhookCandidateId, ['main_vacancy_id'])['candidate'] ?? [];
        $rawPhone = (string) ($candidate['mobile_phone'] ?? '');

        if ($rawPhone === '') {
            Log::channel('app')->info('flow guard: candidate has no mobile phone', ['candidate_id' => $webhookCandidateId]);

            return GuardResult::denied($webhookCandidateId, 'no mobile phone');
        }

        $phone = $this->sanitizePhone($rawPhone);
        $found = $this->findByPhone($estaff, $rawPhone, $phone);

        if ($found === []) {
            // Estaff did not match the phone filter; fall back to the webhook candidate alone.
            Log::channel('estaff')->warning('flow guard: candidate/find returned nothing for phone, using webhook candidate', [
                'candidate_id' => $webhookCandidateId, 'phone' => $phone,
            ]);
            $found = [['id' => $webhookCandidateId, 'main_vacancy_id' => $candidate['main_vacancy_id'] ?? null]];
        }

        $positions = [];
        $allowed = false;
        foreach ($found as $item) {
            $vacancyId = isset($item['main_vacancy_id']) ? (int) $item['main_vacancy_id'] : 0;
            if ($vacancyId <= 0) {
                continue;
            }
            if (! array_key_exists($vacancyId, $positions)) {
                $positions[$vacancyId] = $this->positionId($estaff, $vacancyId);
            }
            if ($positions[$vacancyId] !== null && in_array($positions[$vacancyId], $allowedPositions, true)) {
                $allowed = true;
            }
        }

        $ids = array_values(array_map(fn ($item) => (int) ($item['id'] ?? 0), $found));
        $selectedId = $ids[0] ?: $webhookCandidateId;
        $duplicates = array_values(array_filter(array_slice($ids, 1)));
        $selected = $found[0];
        $mainVacancyId = isset($selected['main_vacancy_id']) ? (int) $selected['main_vacancy_id'] : null;

        if ($duplicates !== []) {
            Log::channel('estaff')->warning('flow guard: duplicate candidates with the same phone', [
                'webhook_candidate_id' => $webhookCandidateId,
                'selected_candidate_id' => $selectedId,
                'duplicates' => $duplicates,
                'phone' => $phone,
                'positions' => $positions,
            ]);
        }

        if (! $allowed) {
            Log::channel('app')->info('flow guard: no candidate with an allowed position_id, stop', [
                'webhook_candidate_id' => $webhookCandidateId,
                'candidates' => $ids,
                'positions' => $positions,
            ]);

            return GuardResult::denied($webhookCandidateId, 'position not allowed', $phone, $duplicates, $positions);
        }

        if ($selectedId !== $webhookCandidateId) {
            Log::channel('app')->info('flow guard: processing first candidate from Estaff answer instead of webhook one', [
                'webhook_candidate_id' => $webhookCandidateId, 'selected_candidate_id' => $selectedId,
            ]);
        }

        return new GuardResult(true, $selectedId, $phone, $mainVacancyId ?: null, $duplicates, $positions);
    }

    /**
     * Try the phone as stored in Estaff first, then the normalized 11-digit form.
     */
    private function findByPhone(object $estaff, string $rawPhone, string $phone): array
    {
        $variants = array_values(array_unique(array_filter([
            trim(explode(',', $rawPhone)[0]),
            $phone,
            $this->normalizePhone11($rawPhone),
        ])));

        foreach ($variants as $variant) {
            $found = $estaff->findCandidatesByPhone($variant);
            if ($found !== []) {
                return $found;
            }
        }

        return [];
    }

    private function positionId(object $estaff, int $vacancyId): ?string
    {
        return Cache::remember("flow:vacancy_position:$vacancyId", self::POSITION_CACHE_TTL, function () use ($estaff, $vacancyId) {
            try {
                return $estaff->getVacancyPositionId($vacancyId);
            } catch (\Throwable $e) {
                Log::channel('estaff')->error('flow guard: vacancy position lookup failed', [
                    'vacancy_id' => $vacancyId, 'message' => $e->getMessage(),
                ]);

                return;
            }
        });
    }
}
