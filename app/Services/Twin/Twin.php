<?php

namespace App\Services\Twin;

use App\Models\CallTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Twin
{
    private $config;

    private $client;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new TwinClient($config);
    }

    public function sendMessage(string $phone, int $id, array $vars)
    {
        Log::channel('twin')->info(__FUNCTION__.' prepare', ['phone' => $phone, 'candidate_id' => $id, 'vars' => $vars]);
        $today = Carbon::now()->format('Y-m-d');
        $data = [
            'messages' => [
                [
                    'useShortLinks' => false,
                    'channels' => [
                        'chat' => [
                            'chatId' => $this->config['chat_id'],
                            'botId' => $this->config['bot_id'],
                            'messengerType' => 'WHATSAPP',
                            'chatSessionName' => 'WA'.$today,
                            'provider' => 'TWIN',
                        ],
                    ],
                    'allowedTimeRanges' => [
                        [
                            $this->config['allowed_time_from'],
                            $this->config['allowed_time_to'],
                        ],
                    ],
                    'destinations' => [
                        [
                            'variables' => $vars,
                            'phone' => $phone,
                        ],
                    ],
                    'callbackData' => "$id",
                    'callbackUrl' => config('app.external_url').'/api/twin-webhooks',
                ],
            ],
        ];

        Log::channel('twin')->info(__FUNCTION__.' send', $data);
        $result = $this->client->post('https://notify.twin24.ai/api/v1/messages', $data);
        Log::channel('twin')->info(__FUNCTION__.' get', $result);

        return $result;
    }

    public function sendMessageCold(string $phone, int $id, array $vars)
    {
        Log::channel('twin')->info(__FUNCTION__.' prepare', ['phone' => $phone, 'candidate_id' => $id, 'vars' => $vars]);
        $today = Carbon::now()->format('Y-m-d');
        $data = [
            'messages' => [
                [
                    'useShortLinks' => false,
                    'channels' => [
                        'chat' => [
                            'chatId' => $this->config['chat_id'],
                            'botId' => $this->config['cold_bot_id'],
                            'messengerType' => 'WHATSAPP',
                            'chatSessionName' => 'WA'.$today.'Холодный',
                            'provider' => 'TWIN',
                        ],
                    ],
                    'allowedTimeRanges' => [
                        [
                            $this->config['allowed_time_from'],
                            $this->config['allowed_time_to'],
                        ],
                    ],
                    'destinations' => [
                        [
                            'variables' => $vars,
                            'phone' => $phone,
                        ],
                    ],
                    'callbackData' => "$id",
                    'callbackUrl' => config('app.external_url').'/api/twin-webhooks',
                ],
            ],
        ];

        Log::channel('twin')->info(__FUNCTION__.' send', $data);
        $result = $this->client->post('https://notify.twin24.ai/api/v1/messages', $data);
        Log::channel('twin')->info(__FUNCTION__.' get', $result);

        return $result;
    }

    public function sendSms(string $phone)
    {
        $data = [
            'messages' => [
                [
                    'useShortLinks' => false,
                    'channels' => [
                        'sms' => [
                            'text' => $this->config['sms_text'],
                            'from' => $this->config['sms_from'],
                        ],
                    ],
                    'destinations' => [
                        [
                            'phone' => $phone,
                        ],
                    ],
                ],
            ],
        ];

        Log::channel('twin')->info(__FUNCTION__.' send', $data);
        $result = $this->client->post('https://notify.twin24.ai/api/v1/messages', $data);
        Log::channel('twin')->info(__FUNCTION__.' get', $result);

        return $result;
    }

    public function getCallTask(): string
    {
        $today = Carbon::now()->format('Y-m-d');
        $type = $this->config['call_type'];

        $task = CallTask::whereDate('date', '=', $today)
            ->where('type', $type)
            ->pluck('twin_id')
            ->first();

        if (empty($task)) {
            Log::channel('twin')->info('CallTask not found in DB - creating it', [
                'date' => $today,
                'type' => $type,
            ]);
            $task = $this->makeCallTask($type);
        }

        return $task;
    }

    private function makeCallTask(string $type): string
    {
        $today = Carbon::now()->format('Y-m-d');

        $data = [
            'additionalOptions' => [
                'recordCall' => true,
                'recTrimLeft' => false,
                'fullListMethod' => 'reject',
                'fullListTime' => 13,
                'useTr' => true,
                'allowCallTimeFrom' => $this->config['allow_call_time_from'],
                'allowCallTimeTo' => $this->config['allow_call_time_to'],
                'detectRobot' => false,
                'providerId' => $this->config['provider_id'],
            ],
            'redialStrategyOptions' => [
                'redialStrategyEn' => true,
                'busy' => [
                    'redial' => true,
                    'time' => 1800,
                    'count' => 3,
                ],
                'noAnswer' => [
                    'redial' => true,
                    'time' => 7200,
                    'count' => 5,
                ],
                'answerMash' => [
                    'redial' => false,
                ],
                'congestion' => [
                    'redial' => true,
                    'time' => 900,
                    'count' => 5,
                ],
                'answerNoList' => [
                    'redial' => true,
                    'time' => 3600,
                    'count' => 2,
                ],
                'candidateLimit' => [
                    'redial' => true,
                    'count' => 6,
                ],
                'numberLimit' => [
                    'redial' => true,
                    'count' => 6,
                ],
            ],
            'name' => 'CALL'.$today.'*'.$type,
            'defaultExec' => 'robot',
            'defaultExecData' => $this->config['default_exec'],
            'secondExec' => 'ignore',
            'cidType' => 'gornum',
            'startType' => 'manual',
            'cps' => '0.97',
            'cidData' => $this->config['cid'],
            'webhookUrls' => [],
            'callbackData' => [],
        ];

        Log::channel('twin')->info(__FUNCTION__.' send', $data);
        $result = $this->client->post('https://cis.twin24.ai/api/v1/telephony/autoCall', $data);
        Log::channel('twin')->info(__FUNCTION__.' get', $result);

        if (! empty($result['id']['identity'])) {
            CallTask::create([
                'date' => $today,
                'type' => $type,
                'twin_id' => $result['id']['identity'],
            ]);

            return $result['id']['identity'];
        }

        throw new \Exception('CallTask false');
    }

    public function makeCallToCandidate(string $callId, string $phone, int $candidate)
    {
        $data = [
            'batch' => [
                [
                    'callbackData' => [
                        'EStaffID' => "$candidate",
                    ],
                    'phone' => [$phone],
                    'variables' => [
                        'EStaffID' => "$candidate",
                    ],
                    'autoCallId' => $callId,
                ],
            ],
            'forceStart' => true,
        ];
        Log::channel('twin')->info(__FUNCTION__.' send', $data);
        $result = $this->client->post('https://cis.twin24.ai/api/v1/telephony/autoCallCandidate/batch', $data);
        Log::channel('twin')->info(__FUNCTION__.' get', $result);

        return $result;
    }

    public function getDataCall(string $taskId, string $id)
    {
        Log::channel('twin')->info(__FUNCTION__.' send', ['taskId' => $taskId, 'id' => $id]);
        $result = $this->client->get('https://twin24.ai/analyse/api/v1/search/cis/sessions?fields=currentStatusName,
         number&taskId='.$taskId.'&id='.$id);
        Log::channel('twin')->info(__FUNCTION__.' get', $result);

        return $result;
    }
}
