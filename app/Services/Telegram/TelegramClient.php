<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramClient
{
    private string $baseUrl;

    public function __construct(private array $config)
    {
        $this->baseUrl = 'https://api.telegram.org/bot'.$this->config['bot_token'];
    }

    public function sendMessage(string $chatId, string $text, string $parseMode = 'Markdown'): array
    {
        Log::channel('app')->info(__FUNCTION__.' send', ['chat_id' => $chatId]);

        $response = Http::post("{$this->baseUrl}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ]);

        $data = $response->json() ?? [];

        Log::channel('app')->info(__FUNCTION__.' get', ['data' => $data]);

        if (! $response->successful()) {
            throw new \Exception('Telegram API error: '.$response->body());
        }

        return $data;
    }
}
