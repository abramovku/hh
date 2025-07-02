<?php

namespace App\Jobs;

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
            "adress" => $vacancyData['vacancy']['division_name'],
            "salary" => $vacancyData['vacancy']['salary']
        ];

        $phone = str_replace(['+', '(', ')', '-', ' '], '', $candidateData['candidate']['mobile_phone']);

        $TwinService->sendMessage($phone, $candidate->candidate_estaff, $vars);
        Log::channel('app')->info("WHATSAPP message created", ['candidate_id' => $candidate->candidate_estaff]);
    }
}
