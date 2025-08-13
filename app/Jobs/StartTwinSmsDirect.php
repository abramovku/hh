<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class StartTwinSmsDirect implements ShouldQueue
{
    use Queueable;

    private string $phone;
    private string $candidate;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $candidate)
    {
        $this->phone = $phone;
        $this->candidate = $candidate;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('app')->info("Start sms message direct", ['phone' => $this->phone]);

        $TwinService = app('twin');
        $EstaffService = app('estaff');

        $phone = str_replace(['+', '(', ')', '-', ' '], '', $this->phone);

        $data = $TwinService->sendSms($phone);
        if (!empty($data[0]['id'])) {
            Log::channel('app')->info("Sms created", ['phone' => $this->phone]);
        } else {
            Log::channel('app')->info("Sms not created", ['phone' => $this->phone]);
        }

        if (!empty($this->candidate)) {
            $params = [
                "candidate" => [
                    "id" => intval($this->candidate),
                    "state_id" => "event_type_51"
                ]
            ];

            try {
                $EstaffService->setStateCandidate($params);
            } catch (\Exception $e) {
                Log::channel('app')->error(
                    'change status after sms direct error',
                    [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]
                );
            }

        }
    }
}
