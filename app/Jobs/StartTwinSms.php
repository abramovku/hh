<?php

namespace App\Jobs;

use App\Dictionaries\TestPhones;
use App\Models\Response;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class StartTwinSms implements ShouldQueue
{
    use Queueable;

    private int $id;
    /**
     * Create a new job instance.
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('app')->info("Start sms message", ['response_id' => $this->id]);
        $EstaffService = app('estaff');
        $TwinService = app('twin');

        $candidateData = $EstaffService->getCandidate($this->id);

        if (empty($candidateData['candidate']['mobile_phone'])) {
            Log::channel('app')->info("Where's no mobile phone for message", ['candidate_id' => $this->id]);
            return;
        }

        $phone = str_replace(['+', '(', ')', '-', ' '], '', $candidateData['candidate']['mobile_phone']);

        if (!in_array($phone, TestPhones::PHONES)){
            Log::channel('app')->info("Phone not for test - reject", ['candidate_id' => $this->id]);
            return;
        }

        $data = $TwinService->sendSms($phone);
        if (!empty($data[0]['id'])) {
            Log::channel('app')->info("Sms created", ['candidate_id' => $this->id]);
        } else {
            Log::channel('app')->info("Sms not created", ['candidate_id' => $this->id]);
        }
    }
}
