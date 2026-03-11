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

    private function call(string $method, string $endpoint, array $params): array
    {
        Log::channel('estaff')->info($method.' send', $params);
        $data = $this->client->post($endpoint, $params);
        Log::channel('estaff')->info($method.' get', $data);

        return $data;
    }

    public function findVacancy(int $id): array
    {
        Log::channel('estaff')->info(__FUNCTION__.' send', ['id' => $id]);

        foreach (['cs_id_hh_1', 'cs_hh_add1', 'cs_hh_add2'] as $field) {
            $params = [
                'filter' => [
                    $field => "$id",
                ],
                'field_names' => ['user_id'],
            ];
            $data = $this->client->post('vacancy/find', $params);
            if (! empty($data['vacancies'][0])) {
                Log::channel('estaff')->info(__FUNCTION__.' get', ['id' => $id, 'field' => $field]);

                return $data['vacancies'][0];
            }
        }

        Log::channel('estaff')->info(__FUNCTION__.' not found', ['id' => $id]);

        return [];
    }

    public function getVacancy(int $id, array $fields = []): array
    {
        Log::channel('estaff')->info(__FUNCTION__.' send', ['id' => $id]);
        $result_fields = array_merge(['name', 'division_name', 'salary', 'cs_adress_intr', 'max_salary'], $fields);
        $params = [
            'vacancy' => [
                'id' => $id,
            ],
            'field_names' => $result_fields,
        ];

        $data = $this->client->post('vacancy/get', $params);
        Log::channel('estaff')->info(__FUNCTION__.' get', ['id' => $id]);

        return $data;
    }

    public function getCandidate(int $id, array $fields = []): array
    {
        Log::channel('estaff')->info(__FUNCTION__.' send', ['id' => $id]);
        $result_fields = array_merge(['mobile_phone'], $fields);
        $params = [
            'candidate' => [
                'id' => $id,
            ],
            'field_names' => $result_fields,
        ];

        $data = $this->client->post('candidate/get', $params);
        Log::channel('estaff')->info(__FUNCTION__.' get', ['id' => $id]);

        return $data;
    }

    public function addResponse(array $params): array
    {
        return $this->call(__FUNCTION__, 'candidate/add', $params);
    }

    public function findVacancyFull(array $params): array
    {
        return $this->call(__FUNCTION__, 'vacancy/find', $params);
    }

    public function getCandidateFull(array $params): array
    {
        return $this->call(__FUNCTION__, 'candidate/get', $params);
    }

    public function findCandidateFull(array $params): array
    {
        return $this->call(__FUNCTION__, 'candidate/find', $params);
    }

    public function getVacancyFull(array $params): array
    {
        return $this->call(__FUNCTION__, 'vacancy/get', $params);
    }

    public function changeCandidate(array $params): array
    {
        return $this->call(__FUNCTION__, 'candidate/change', $params);
    }

    public function setStateCandidate(array $params): array
    {
        return $this->call(__FUNCTION__, 'candidate/set_state', $params);
    }

    public function eventCandidate(array $params): array
    {
        return $this->call(__FUNCTION__, 'candidate/add_event', $params);
    }

    public function setWebhook(array $params): array
    {
        return $this->call(__FUNCTION__, 'webhook/set', $params);
    }

    public function getWebhooks(): array
    {
        Log::channel('estaff')->info(__FUNCTION__.' send');
        $data = $this->client->post('webhook/get', []);
        Log::channel('estaff')->info(__FUNCTION__.' get', $data);

        return $data;
    }

    public function deleteWebhook(string $id): array
    {
        return $this->call(__FUNCTION__, 'webhook/delete', ['id' => $id]);
    }
}
