<?php

namespace App\Jobs;

use App\Dictionaries\TestPhones;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class StartTwinColdConversation implements ShouldQueue
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
        Log::channel('app')->info("Start message batch cold candidate", ['candidate_id' => $this->candidate]);
        $EstaffService = app('estaff');
        $TwinService = app('twin');

        $candidateData = $EstaffService->getCandidate($this->candidate);

        if (empty($candidateData['candidate']['mobile_phone'])) {
            Log::channel('app')->info("Where's no mobile phone for message", ['candidate_id' => $this->candidate]);
            return;
        }

        $vars = [
            "EStaffID" => "$this->candidate",
        ];

        $phone = str_replace(['+', '(', ')', '-', ' '], '', $candidateData['candidate']['mobile_phone']);

        /*if (!in_array($phone, TestPhones::PHONES)){
            Log::channel('app')->info("Phone not for test - reject", ['candidate_id' => $this->candidate]);
            return;
        }*/

        $data = $TwinService->sendMessageCold($phone, $this->candidate, $vars);

        if (!empty($data[0]['id'])) {
            Log::channel('app')->info("WHATSAPP cold message created", ['candidate_id' => $this->candidate]);
        } else {
            Log::channel('app')->info("WHATSAPP cold message not created", ['candidate_id' => $this->candidate]);
        }
    }
}
