<?php

namespace App\Jobs\Flow;

use App\Services\Flow\CandidateGuard;
use App\Services\Flow\CandidateLocationEnricher;
use App\Traits\RecordsContactEvent;
use App\Traits\SanitizesPhone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Estaff event_type_47 (warm) / event_type_48 (cold): duplicate check, region update for cold,
 * add the candidate to today's Twin autoCall of the right type, set event_type_88 (ТЗ 3.2, 3.3, 4).
 */
class StartFlowCall implements ShouldQueue
{
    use Dispatchable, Queueable, RecordsContactEvent, SanitizesPhone;

    public function __construct(
        private readonly int $candidateId,
        private readonly string $taskKey,
        private readonly ?int $vacancyId = null,
    ) {}

    public function handle(): void
    {
        Log::channel('app')->info('flow call: start', [
            'candidate_id' => $this->candidateId, 'task' => $this->taskKey, 'vacancy_id' => $this->vacancyId,
        ]);

        $guard = app(CandidateGuard::class)->check($this->candidateId);
        if (! $guard->allowed) {
            Log::channel('app')->info('flow call: stopped by guard', ['candidate_id' => $this->candidateId, 'reason' => $guard->reason]);

            return;
        }

        $taskKey = $this->taskKey;

        if ($taskKey === 'cold') {
            app(CandidateLocationEnricher::class)->updateCandidateLocation($guard->candidateId, $guard->phone);

            $vacancyId = $this->vacancyId ?: $guard->mainVacancyId;
            if ($vacancyId && in_array((string) $vacancyId, array_map('strval', (array) config('flow.old_script_vacancy_ids', [])), true)) {
                $taskKey = 'old_script';
            }
        }

        $twin = app('twin');
        $created = false;
        $autoCallId = $twin->getAutoCall($taskKey, null, $created);

        if ($created && (int) config('flow.sleep_after_create', 0) > 0) {
            sleep((int) config('flow.sleep_after_create'));
        }

        $twin->addCandidateToAutoCall(
            $autoCallId,
            $guard->phone,
            (string) $guard->candidateId,
            $this->normalizePhone11($guard->phone) ?? $guard->phone
        );

        Log::channel('app')->info('flow call: candidate added to autoCall', [
            'candidate_id' => $guard->candidateId, 'task' => $taskKey, 'autocall_id' => $autoCallId,
        ]);

        try {
            app('estaff')->setStateCandidate([
                'candidate' => ['id' => $guard->candidateId, 'state_id' => config('flow.states.before_call')],
            ]);
        } catch (\Throwable $e) {
            Log::channel('app')->error('flow call: set_state before call failed', [
                'candidate_id' => $guard->candidateId, 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
        }

        $this->recordContact($guard->candidateId, 'call');
    }
}
