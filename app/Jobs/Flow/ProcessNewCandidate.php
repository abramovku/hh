<?php

namespace App\Jobs\Flow;

use App\Services\Flow\CandidateGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Estaff state `new` (ТЗ 3.4): only the duplicate check is performed and logged.
 */
class ProcessNewCandidate implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(private readonly int $candidateId) {}

    public function handle(): void
    {
        $guard = app(CandidateGuard::class)->check($this->candidateId);

        Log::channel('app')->info('flow new candidate: checked', [
            'candidate_id' => $this->candidateId,
            'allowed' => $guard->allowed,
            'duplicates' => $guard->duplicates,
            'reason' => $guard->reason,
        ]);
    }
}
