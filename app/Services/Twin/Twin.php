<?php

namespace App\Services\Twin;

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
        Log::channel('twin')->info(__FUNCTION__ . ' prepare', ['phone' => $phone, 'candidate_id' => $id, 'vars' => $vars]);
        $today = Carbon::now()->format('Y-m-d');
        $data = [
            "messages" => [
                [
                    "useShortLinks" => false,
                    "channels" => [
                        "chat" => [
                            "chatId" => $this->config['chat_id'],
                            "botId" => $this->config['bot_id'],
                            "messengerType" => "WHATSAPP",
                            "chatSessionName" => "WA" . $today ,
                            "provider" => "TWIN"
                        ],
                    ],
                    "allowedTimeRanges" => [
                        [
                            "9:00:00",
                            "22:00:00"
                        ]
                    ],
                    "destinations" => [
                        [
                            "variables" => $vars,
                            "phone" => $phone
                        ]
                    ],
                    "callbackData" => "$id",
                    "callbackUrl" => config('app.external_url') . '/api/twin-webhooks'
                ]
            ]
        ];

        Log::channel('twin')->info(__FUNCTION__ . ' send', $data);
        $result = $this->client->post('https://notify.twin24.ai/api/v1/messages', $data);
        Log::channel('twin')->info(__FUNCTION__ . ' get', $result);
        return $result;
    }

    public function sendSms(string $phone)
    {
        $data = [
            "messages" => [
                [
                    "useShortLinks" => false,
                    "channels" => [
                        "sms" => [
                            "text" => "Вакансия Продавец в GJ, написать нам в ТГ: https://t.me/GJ_seller_bot",
                            "from" => "GloriaJeans"
                        ],
                    ],
                    "destinations" => [
                        [
                            "phone" => $phone
                        ]
                    ]
                ]
            ]
        ];

        Log::channel('twin')->info(__FUNCTION__ . ' send', $data);
        $result = $this->client->post('https://notify.twin24.ai/api/v1/messages', $data);
        Log::channel('twin')->info(__FUNCTION__ . ' get', $result);
        return $result;
    }

    public function makeCallTask(int $candidate_id)
    {
        $today = Carbon::now()->format('Y-m-d');
        $data = [
            "additionalOptions" => [
                "recordCall" => true,
                "recTrimLeft" => false,
                "fullListMethod" => "reject",
                "fullListTime" => 13,
                "useTr" => true,
                "allowCallTimeFrom" => 32400,
                "allowCallTimeTo" => 79200,
                "detectRobot" => false,
                "providerId" => $this->config['provider_id']
            ],
            "redialStrategyOptions" => [
                "redialStrategyEn" => true,
                "busy" => [
                    "redial" => true,
                    "time" => 1800,
                    "count" => 3
                ],
                "noAnswer" => [
                    "redial" => true,
                    "time" => 900,
                    "count" => 5
                ],
                "answerMash" => [
                    "redial" => false
                ],
                "congestion" => [
                    "redial" => true,
                    "time" => 900,
                    "count" => 5
                ],
                "answerNoList" => [
                    "redial" => true,
                    "time" => 1800,
                    "count" => 2
                ],
                "candidateLimit" => [
                    "redial" => true,
                    "count" => 6
                ],
                "numberLimit" => [
                    "redial" => true,
                    "count" => 6
                ]
            ],
            "name" => "CALL" . $today . "*" . $candidate_id,
            "defaultExec" => "robot",
            "defaultExecData" => $this->config['default_exec'],
            "secondExec" => "ignore",
            "cidType" => "gornum",
            "startType" => "manual",
            "cps" => "0.97",
            "cidData" => $this->config['cid'],
            "webhookUrls" => [],
            "callbackData" => [],
        ];

        Log::channel('twin')->info(__FUNCTION__ . ' send', $data);
        $result = $this->client->post('https://cis.twin24.ai/api/v1/telephony/autoCall', $data);
        Log::channel('twin')->info(__FUNCTION__ . ' get', $result);
        return $result;
    }

    public function makeCallToCandidate(string $callId, string $phone, int $candidate)
    {
        $data = [
            "batch" => [
                [
                    "callbackData" => [
                        "EStaffID" => "$candidate"
                    ],
                    "phone" => [$phone],
                    "variables" => [
                        "EStaffID" => "$candidate"
                    ],
                    "autoCallId" => $callId
                ]
            ],
            "forceStart" => true,
        ];
        Log::channel('twin')->info(__FUNCTION__ . ' send', $data);
        $result = $this->client->post('https://cis.twin24.ai/api/v1/telephony/autoCallCandidate/batch', $data);
        Log::channel('twin')->info(__FUNCTION__ . ' get', $result);
        return $result;
    }
}



