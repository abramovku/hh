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
        if (empty($this->data['taskId']) || empty($this->data['lastCallId'])) {
            Log::channel('twin')->info("voice webhook missing data", ["data" => $this->data]);
            return;
        }

        sleep(7);

        $data = $TwinService->getDataCall($this->data['taskId'], $this->data['lastCallId']);

        if (!empty($data['items']) && is_array($data['items'])) {

            if (
                !empty($data['items'][0]['currentStatusName'])
                && !empty($data['items'][0]['number'])
                && !in_array($data['items'][0]['currentStatusName'], $flowStatuses)
            ) {
                dispatch(new StartTwinSmsDirect($data['items'][0]['number'],
                    $this->data['callbackData']['EStaffID'] ?? ''));
                Log::channel('twin')->info("voice webhook send sms", ["data" => $this->data]);
                return;
            }
            Log::channel('twin')->info("voice webhook don't send sms", ["data" => $this->data]);
        }
    }
}
