<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Log;

class Telegram
{
    private ?TelegramClient $client;

    private string $chatId;

    public function __construct(private array $config)
    {
        $this->chatId = $config['chat_id'] ?? '';

        if (! empty($config['bot_token']) && ! empty($this->chatId)) {
            $this->client = new TelegramClient($config);
        }
    }

    public function send(string $message): array
    {
        if (! isset($this->client)) {
            Log::channel('app')->warning('Telegram: credentials not configured, message not sent');

            return [];
        }

        return $this->client->sendMessage($this->chatId, $message);
    }
}
