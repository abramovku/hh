<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class OperateTwinVoiceWebhook implements ShouldQueue
{
    use Dispatchable, Queueable;

    private array $data;
    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('twin')->info("start voice webhook", ["data" => $this->data]);
        $flowStatuses = ['ANSWERED', 'DIAL', 'INPROGRESS'];

        $TwinService = app('twin');
        $data = $TwinService->getDataCall($this->data['taskId']);


        if (!empty($data['items']) && is_array($data['items'])) {
            $reversed = array_reverse($data['items']);

            if (
                !empty($reversed[0]['currentStatusName'])
                && !empty($reversed[0]['number'])
                && !in_array($reversed[0]['currentStatusName'], $flowStatuses)
            ) {
                dispatch(new StartTwinSmsDirect($reversed[0]['number'],
                    $this->data['callbackData']['EStaffID'] ?? ''));
                Log::channel('twin')->info("voice webhook send sms", ["data" => $this->data]);
                return;
            }
            Log::channel('twin')->info("voice webhook don't send sms", ["data" => $this->data]);
        }
    }
}
