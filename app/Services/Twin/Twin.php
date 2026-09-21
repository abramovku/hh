<?php

namespace App\Services\Twin;

use App\Models\CallTask;
use App\Support\Flow;
use App\Traits\GeneratesRid;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Twin
{
    use GeneratesRid;

    private $config;

    private $client;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new TwinClient($config);
    }

    public function sendMessage(string $phone, int $id, array $vars)
    {
        $rid = $this->newRid();
        Log::channel('twin')->info(__FUNCTION__.' prepare', ['rid' => $rid, 'phone' => $phone, 'candidate_id' => $id, 'vars' => $vars]);
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

        Log::channel('twin')->info(__FUNCTION__.' send', ['rid' => $rid] + $data);
        $result = $this->client->post('https://notify.twin24.ai/api/v1/messages', $data);
        Log::channel('twin')->info(__FUNCTION__.' get', ['rid' => $rid] + $result);

        return $result;
    }

    public function sendMessageCold(string $phone, int $id, array $vars)
    {
        $rid = $this->newRid();
        Log::channel('twin')->info(__FUNCTION__.' prepare', ['rid' => $rid, 'phone' => $phone, 'candidate_id' => $id, 'vars' => $vars]);
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

        Log::channel('twin')->info(__FUNCTION__.' send', ['rid' => $rid] + $data);
        $result = $this->client->post('https://notify.twin24.ai/api/v1/messages', $data);
        Log::channel('twin')->info(__FUNCTION__.' get', ['rid' => $rid] + $result);

        return $result;
    }

    public function sendSms(string $phone)
    {
        $rid = $this->newRid();
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

        Log::channel('twin')->info(__FUNCTION__.' send', ['rid' => $rid] + $data);
        $result = $this->client->post('https://notify.twin24.ai/api/v1/messages', $data);
        Log::channel('twin')->info(__FUNCTION__.' get', ['rid' => $rid] + $result);

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
            'redialStrategyOptions' => $this->redialStrategy(),
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

        $rid = $this->newRid();
        Log::channel('twin')->info(__FUNCTION__.' send', ['rid' => $rid] + $data);
        $result = $this->client->post('https://cis.twin24.ai/api/v1/telephony/autoCall', $data);
        Log::channel('twin')->info(__FUNCTION__.' get', ['rid' => $rid] + $result);

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
        $rid = $this->newRid();
        Log::channel('twin')->info(__FUNCTION__.' send', ['rid' => $rid] + $data);
        $result = $this->client->post('https://cis.twin24.ai/api/v1/telephony/autoCallCandidate/batch', $data);
        Log::channel('twin')->info(__FUNCTION__.' get', ['rid' => $rid] + $result);

        return $result;
    }

    public function getDataCall(string $taskId, string $id, ?string $estaffId = null)
    {
        $rid = $this->newRid();
        Log::channel('twin')->info(__FUNCTION__.' send', ['rid' => $rid, 'taskId' => $taskId, 'id' => $id, 'estaff_id' => $estaffId]);
        $result = $this->client->get('https://twin24.ai/analyse/api/v1/search/cis/sessions?fields=currentStatusName,
         number&taskId='.$taskId.'&id='.$id);
        Log::channel('twin')->info(__FUNCTION__.' get', ['rid' => $rid, 'estaff_id' => $estaffId] + $result);

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | New flow (config/flow.php)
    |--------------------------------------------------------------------------
    */

    /**
     * Twin autoCall id for a task type and calendar date (business timezone), created on first use.
     * One autoCall per (date, type): guarded by a cache lock and the unique index on call_tasks.
     *
     * @param  bool|null  $created  Set to true when the autoCall was created by this call.
     */
    public function getAutoCall(string $taskKey, ?CarbonInterface $date = null, ?bool &$created = null): string
    {
        $date = $date ? $date->copy()->setTimezone(Flow::timezone()) : Flow::now();
        $day = $date->toDateString();
        $created = false;

        $lock = Cache::lock("flow:autocall:$taskKey:$day", 30);

        return $lock->block(25, function () use ($taskKey, $date, $day, &$created) {
            $existing = CallTask::where('date', $day)->where('type', $taskKey)->value('twin_id');
            if (! empty($existing)) {
                return $existing;
            }

            Log::channel('twin')->info('autoCall not found in DB - creating it', ['date' => $day, 'type' => $taskKey]);
            $twinId = $this->makeAutoCall($taskKey, $date);

            try {
                CallTask::create(['date' => $day, 'type' => $taskKey, 'twin_id' => $twinId]);
                $created = true;
            } catch (QueryException $e) {
                // Unique (date, type) hit: another worker created it between our read and write.
                $existing = CallTask::where('date', $day)->where('type', $taskKey)->value('twin_id');
                Log::channel('twin')->warning('autoCall created concurrently, reusing stored id', [
                    'date' => $day, 'type' => $taskKey, 'created' => $twinId, 'stored' => $existing,
                ]);
                if (! empty($existing)) {
                    return $existing;
                }
                throw $e;
            }

            return $twinId;
        });
    }

    /**
     * POST telephony/autoCall with the payload from ТЗ (4.3–4.5, 6.2, 6.6).
     */
    private function makeAutoCall(string $taskKey, CarbonInterface $date): string
    {
        $task = Flow::task($taskKey);

        $data = [
            'additionalOptions' => [
                'recordCall' => true,
                'recTrimLeft' => true,
                'fullListMethod' => 'reject',
                'fullListTime' => 13,
                'detectRobot' => false,
                'providerId' => $this->config['provider_id'],
                'allowCallTimeFrom' => (int) $task['from'],
                'allowCallTimeTo' => (int) $task['to'],
            ],
            'redialStrategyOptions' => $this->redialStrategy(),
            'name' => Flow::taskName($taskKey, $date),
            'defaultExec' => 'robot',
            'secondExec' => 'ignore',
            'defaultExecData' => $task['bot'],
            'cidType' => 'gornum',
            'cidData' => $this->config['cid'],
            'startType' => 'manual',
            'cps' => '0.97',
        ];

        $rid = $this->newRid();
        Log::channel('twin')->info(__FUNCTION__.' send', ['rid' => $rid, 'task' => $taskKey] + $data);
        $result = $this->client->post(config('flow.urls.autocall'), $data);
        Log::channel('twin')->info(__FUNCTION__.' get', ['rid' => $rid, 'task' => $taskKey] + $result);

        if (empty($result['id']['identity'])) {
            throw new \Exception("autoCall [$taskKey] was not created: no id in Twin response");
        }

        return $result['id']['identity'];
    }

    /**
     * POST telephony/autoCallCandidate (ТЗ 4.6). clientExternalId protects the task from duplicates:
     * Twin answers 200 for a repeated phone and adds the candidate only once.
     */
    public function addCandidateToAutoCall(
        string $autoCallId,
        string $phone,
        string $estaffId,
        ?string $clientExternalId = null,
        array $vars = []
    ): array {
        $data = [
            'batch' => [
                [
                    'variables' => ['EStaffID' => $estaffId] + $vars,
                    'callbackData' => ['EStaffID' => $estaffId],
                    'autoCallId' => $autoCallId,
                    'phone' => [$phone],
                    'clientExternalId' => $clientExternalId ?? $phone,
                    'forceStart' => true,
                ],
            ],
        ];

        $rid = $this->newRid();
        Log::channel('twin')->info(__FUNCTION__.' send', ['rid' => $rid] + $data);
        $result = $this->client->post(config('flow.urls.autocall_candidate'), $data);
        Log::channel('twin')->info(__FUNCTION__.' get', ['rid' => $rid] + $result);

        return $result;
    }

    /**
     * GET analyse sessions by phone and call start (ТЗ 5.3). Returns the decoded body (`count`, `items`).
     */
    public function findSessions(string $phone, string $from, int $limit = 1): array
    {
        $query = http_build_query([
            'fields' => config('flow.analyse.fields'),
            'limit' => $limit,
            'from' => $from,
            'phone' => $phone,
        ]);

        $rid = $this->newRid();
        Log::channel('twin')->info(__FUNCTION__.' send', ['rid' => $rid, 'phone' => $phone, 'from' => $from]);
        $result = $this->client->get(config('flow.urls.analyse_sessions').'?'.$query);
        Log::channel('twin')->info(__FUNCTION__.' get', ['rid' => $rid, 'phone' => $phone] + $result);

        return $result;
    }

    private function redialStrategy(): array
    {
        return [
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
        ];
    }
}
