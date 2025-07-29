<?php

namespace App\Jobs;

use App\Dictionaries\TestPhones;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class StartTwinManualConversation implements ShouldQueue
{
    use Queueable;
    private int $vacancy;
    private int $candidate;

    /**
     * Create a new job instance.
     */
    public function __construct(int $vacancy, int $candidate)
    {
        $this->vacancy = $vacancy;
        $this->candidate = $candidate;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('app')->info("Start message batch manual candidate", ['candidate_id' => $this->candidate]);
        $EstaffService = app('estaff');
        $TwinService = app('twin');

        $vacancyData = $EstaffService->getVacancy($this->vacancy);
        $candidateData = $EstaffService->getCandidate($this->candidate);

        if (empty($candidateData['candidate']['mobile_phone'])) {
            Log::channel('app')->info("Where's no mobile phone for message", ['candidate_id' => $this->candidate]);
            return;
        }

        $vars = [
            "vacancy_name" => $vacancyData['vacancy']['name'],
            "adress" => $vacancyData['vacancy']['cs_adress_intr'],
            "salary" => $vacancyData['vacancy']['max_salary'],
            "EStaffID" => "$this->candidate",
        ];

        $phone = str_replace(['+', '(', ')', '-', ' '], '', $candidateData['candidate']['mobile_phone']);

        /*if (!in_array($phone, TestPhones::PHONES)){
            Log::channel('app')->info("Phone not for test - reject", ['candidate_id' => $this->candidate]);
            return;
        }*/

        $data = $TwinService->sendMessage($phone, $this->candidate, $vars);

        if (!empty($data[0]['id'])) {
            Log::channel('app')->info("WHATSAPP message created", ['candidate_id' => $this->candidate]);
        } else {
            Log::channel('app')->info("WHATSAPP message not created", ['candidate_id' => $this->candidate]);
        }
    }
}
