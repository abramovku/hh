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
        Log::channel('twin')->info(__FUNCTION__ . ' send', ['phone' => $phone, 'candidate_id' => $id, 'vars' => $vars]);
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
                        "allowedTimeRanges" => [
                            [
                                "9:00:00",
                                "22:00:00"
                            ]
                        ],
                        "destinations" => [
                            [
                                "variables" => $vars,
                                "phone" => "79788388242"//$phone
                            ]
                        ],
                        "callbackData" => "$id",
                        "callbackUrl" => config('app.external_url') . '/twin-webhooks'
                    ]
                ]
            ]
        ];

        $result = $this->client->post('https://notify.twin24.ai/api/v1/messages', $data);
        Log::channel('twin')->info(__FUNCTION__ . ' get', $result);
        return $data;
    }

    public function makeCall()
    {
        $data = [];
        $this->client->post('https://cis.twin24.ai/api/v1/telephony/autoCall', $data);
    }
}

