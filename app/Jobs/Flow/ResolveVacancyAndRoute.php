<?php

namespace App\Jobs\Flow;

use App\Services\Flow\FlowRouter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Hybrid mode: an Estaff webhook without vacancy_id — read the candidate's main_vacancy_id
 * from Estaff, then let FlowRouter pick legacy or new flow.
 */
class ResolveVacancyAndRoute implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(private readonly array $data) {}

    public function handle(): void
    {
        $candidateId = (int) ($this->data['data']['candidate_id'] ?? 0);
        $vacancyId = 0;

        try {
            $candidate = app('estaff')->getCandidate($candidateId, ['main_vacancy_id'])['candidate'] ?? [];
            $vacancyId = (int) ($candidate['main_vacancy_id'] ?? 0);

            if ($vacancyId === 0) {
                Log::channel('estaff')->warning('hybrid: candidate has no main_vacancy_id, routing to legacy', ['candidate_id' => $candidateId]);
            }
        } catch (\Throwable $e) {
            Log::channel('estaff')->error('hybrid: cannot resolve candidate vacancy, routing to legacy', [
                'candidate_id' => $candidateId, 'message' => $e->getMessage(),
            ]);
        }

        app(FlowRouter::class)->routeEstaffState($this->data, $vacancyId);
    }
}
