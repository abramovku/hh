<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StartTwinCall implements ShouldQueue
{
    use Queueable;

    private int $candidate;
    /**
     * Create a new job instance.
     */
    public function __construct(int $candidate)
    {
        $this->candidate = $candidate;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $TwinService = app('twin');
        $EstaffService = app('estaff');
        $candidateData = $EstaffService->getCandidate($this->candidate);

        if (empty($candidateData['candidate']['mobile_phone'])) {
            Log::channel('app')->info("Where's no mobile phone for call", ['candidate_id'
            => $this->candidate]);
            return;
        }

        $phone = str_replace(['+', '(', ')', '-', ' '], '', $candidateData['candidate']['mobile_phone']);

        $getData = $TwinService->makeCallTask();

        if (!empty($getData['id']['identity'])) {
            sleep(6);
            $TwinService->makeCallToCandidate($getData['id']['identity'], $phone);
        }
    }
}
