<?php

namespace App\Services\Estaff;

use Illuminate\Support\Facades\Log;

class Estaff
{
    private $config;
    private $client;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new EstaffClient($config);
    }

    public function findVacancy(int $id): array
    {
        Log::channel('estaff')->info(__FUNCTION__ . ' send', ['id' => $id]);
        $params = [
            'filter' => [
                'eid' => $id
            ]
        ];
        $data = $this->client->post('vacancy/find', $params);
        Log::channel('estaff')->info(__FUNCTION__ . ' get');
        return $data['vacancies'][0] ?? [];
    }

    public function addResponse(array $params): array
    {
        Log::channel('estaff')->info(__FUNCTION__ . ' send', $params);
        $data = $this->client->post('candidate/add', $params);
        Log::channel('estaff')->info(__FUNCTION__ . ' get', $data);
        return $data;
    }

    public function setWebhook(array $params): array
    {
        Log::channel('estaff')->info(__FUNCTION__ . ' send', $params);
        $data = $this->client->post('webhook/set', $params);
        Log::channel('estaff')->info(__FUNCTION__ . ' get', $data);
        return $data;
    }

    public function getWebhooks(): array
    {
        Log::channel('estaff')->info(__FUNCTION__ . ' send');
        $data = $this->client->post('webhook/get', []);
        Log::channel('estaff')->info(__FUNCTION__ . ' get', $data);
        return $data;
    }

    public function deleteWebhook(string $id): array
    {
        Log::channel('estaff')->info(__FUNCTION__ . ' send', ['id' => $id]);
        $data = $this->client->post('webhook/delete', ['id' => $id]);
        Log::channel('estaff')->info(__FUNCTION__ . ' get', $data);
        return $data;
    }
}
