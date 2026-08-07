<?php

namespace App\Jobs;

use App\Enums\EstaffEvent;
use App\Traits\RecordsContactEvent;
use App\Traits\SanitizesPhone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StartTwinCall implements ShouldQueue
{
    use Dispatchable, Queueable, RecordsContactEvent, SanitizesPhone;

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
        Log::channel('app')->info('Start twin call job', ['candidate' => $this->candidate]);
        $TwinService = app('twin');
        $EstaffService = app('estaff');
        $candidateData = $EstaffService->getCandidate($this->candidate);

        if (empty($candidateData['candidate']['mobile_phone'])) {
            Log::channel('app')->info("Where's no mobile phone for call", ['candidate_id' => $this->candidate]);

            return;
        }

        $phone = $this->sanitizePhone($candidateData['candidate']['mobile_phone']);
        Log::channel('app')->info('Start twin task for call', ['candidate' => $this->candidate]);
        $task = $TwinService->getCallTask();
        sleep(6);

        Log::channel('app')->info('Start twin call to candidate', ['candidate' => $this->candidate]);
        $TwinService->makeCallToCandidate($task, $phone, $this->candidate);

        $params = [
            'candidate' => [
                'id' => $this->candidate,
                'state_id' => EstaffEvent::BeforeCall->value,
            ],
        ];

        try {
            $EstaffService->setStateCandidate($params);
            Log::channel('twin')->info("Candidate status before call changed", ['data' => $this->candidate]);
        } catch (\Exception $e) {
            Log::channel('app')->error(
                'candidate status before call change status error',
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }

        $this->recordContact($this->candidate, 'call');
    }
}
