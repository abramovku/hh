<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class StartTwinSmsDirect implements ShouldQueue
{
    use Queueable;

    private string $phone;
    /**
     * Create a new job instance.
     */
    public function __construct(string $phone)
    {
        $this->phone = $phone;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('app')->info("Start sms message direct", ['phone' => $this->phone]);

        $TwinService = app('twin');

        $phone = str_replace(['+', '(', ')', '-', ' '], '', $this->phone);

        $data = $TwinService->sendSms($phone);
        if (!empty($data[0]['id'])) {
            Log::channel('app')->info("Sms created", ['phone' => $this->phone]);
        } else {
            Log::channel('app')->info("Sms not created", ['phone' => $this->phone]);
        }
    }
}
