<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Enums\EstaffEvent;

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
        Log::channel('twin')->info('start voice webhook', ['data' => $this->data]);
        $flowStatuses = ['ANSWERED', 'DIAL', 'INPROGRESS'];

        $TwinService = app('twin');
        $EstaffService = app('estaff');

        if (empty($this->data['taskId']) || empty($this->data['lastCallId'])) {
            Log::channel('twin')->info('voice webhook missing data', ['data' => $this->data]);

            return;
        }

        sleep(7);

        $data = $TwinService->getDataCall($this->data['taskId'], $this->data['lastCallId']);

        if (! empty($data['items']) && is_array($data['items'])) {

            if (
                ! empty($data['items'][0]['currentStatusName'])
                && ! empty($this->data['callbackData']['EStaffID'])
                && ! in_array($data['items'][0]['currentStatusName'], $flowStatuses)
            ) {
                $params = [
                    'candidate' => [
                        'id' => $this->data['callbackData']['EStaffID'],
                        'state_id' => EstaffEvent::VoiceWebhook->value,
                    ],
                ];

                try {
                    $EstaffService->setStateCandidate($params);
                    Log::channel('twin')->info("voice webhook status changed", ['data' => $this->data]);
                } catch (\Exception $e) {
                    Log::channel('app')->error(
                        'voice webhook change status error',
                        [
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]
                    );
                }

                return;
            }
            Log::channel('twin')->info("voice webhook don't need to change status", ['data' => $this->data]);
        }
    }
}
