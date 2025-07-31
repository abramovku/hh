<?php

namespace App\Jobs;

use App\Dictionaries\TestPhones;
use App\Models\Response;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class StartTwinConversation implements ShouldQueue
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
        Log::channel('app')->info("Start message batch hh candidate", ['response_id' => $this->id]);
        $EstaffService = app('estaff');
        $TwinService = app('twin');

        $candidate = Response::findOrFail($this->id);
        $vacancyData = $EstaffService->getVacancy($candidate->vacancy_estaff);
        $candidateData = $EstaffService->getCandidate($candidate->candidate_estaff);

        if (empty($candidateData['candidate']['mobile_phone'])) {
            Log::channel('app')->info("Where's no mobile phone for message", ['candidate_id'
                => $candidate->candidate_estaff]);
            return;
        }

        $vars = [
            "vacancy_name" => $vacancyData['vacancy']['name'],
            "adress" => $vacancyData['vacancy']['cs_adress_intr'],
            "salary" => $vacancyData['vacancy']['max_salary'],
            "EStaffID" => "$candidate->candidate_estaff",
        ];

        $phone = str_replace(['+', '(', ')', '-', ' '], '', $candidateData['candidate']['mobile_phone']);

        /*if (!in_array($phone, TestPhones::PHONES)){
            Log::channel('app')->info("Phone not for test - reject", ['candidate_id' => $candidate->candidate_estaff]);
            return;
        }*/

        $data = $TwinService->sendMessage($phone, $candidate->candidate_estaff, $vars);
        if (!empty($data[0]['id'])) {
            Log::channel('app')->info("WHATSAPP message created", ['candidate_id' => $candidate->candidate_estaff]);
        } else {
            Log::channel('app')->info("WHATSAPP message not created", ['candidate_id' => $candidate->candidate_estaff]);
        }
    }
}
