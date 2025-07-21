<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;

class StartTwinCall implements ShouldQueue
{
    use Dispatchable, Queueable;

    private int $candidate;
    public string $uuid;

    /**
     * Create a new job instance.
     */
    public function __construct(int $candidate)
    {
        $this->candidate = $candidate;
        $this->uuid = (string) Str::uuid();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('app')->info("Start twin call job", ['candidate' => $this->candidate]);
        $TwinService = app('twin');
        $EstaffService = app('estaff');
        $candidateData = $EstaffService->getCandidate($this->candidate);

        if (empty($candidateData['candidate']['mobile_phone'])) {
            Log::channel('app')->info("Where's no mobile phone for call", ['candidate_id'
            => $this->candidate]);
            return;
        }

        $phone = str_replace(['+', '(', ')', '-', ' '], '', $candidateData['candidate']['mobile_phone']);
        Log::channel('app')->info("Start twin task for call", ['candidate' => $this->candidate]);
        $getData = $TwinService->makeCallTask($this->candidate);

        if (!empty($getData['id']['identity'])) {
            sleep(6);
            Log::channel('app')->info("Start twin call to candidate", ['candidate' => $this->candidate]);
            $TwinService->makeCallToCandidate($getData['id']['identity'], $phone, $this->candidate);
        }
    }
}
