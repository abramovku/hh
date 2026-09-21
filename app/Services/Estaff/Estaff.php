<?php

namespace App\Services\Estaff;

use App\Traits\GeneratesRid;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Log;

class Estaff
{
    use GeneratesRid;

    private $config;

    private $client;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new EstaffClient($config);
    }

    private function call(string $method, string $endpoint, array $params): array
    {
        $rid = $this->newRid();
        Log::channel('estaff')->info($method.' send', ['rid' => $rid] + $params);
        $data = $this->client->post($endpoint, $params);
        Log::channel('estaff')->info($method.' get', ['rid' => $rid] + $data);

        return $data;
    }

    public function findVacancy(int $id): array
    {
        $rid = $this->newRid();
        Log::channel('estaff')->info(__FUNCTION__.' send', ['rid' => $rid, 'id' => $id]);

        foreach (['cs_id_hh_1', 'cs_hh_add1', 'cs_hh_add2'] as $field) {
            $params = [
                'filter' => [
                    $field => "$id",
                ],
                'field_names' => ['user_id'],
            ];
            $data = $this->client->post('vacancy/find', $params);
            if (! empty($data['vacancies'][0])) {
                Log::channel('estaff')->info(__FUNCTION__.' get', ['rid' => $rid, 'id' => $id, 'field' => $field]);

                return $data['vacancies'][0];
            }
        }

        Log::channel('estaff')->info(__FUNCTION__.' not found', ['rid' => $rid, 'id' => $id]);

        return [];
    }

    public function getVacancy(int $id, array $fields = []): array
    {
        $rid = $this->newRid();
        Log::channel('estaff')->info(__FUNCTION__.' send', ['rid' => $rid, 'id' => $id]);
        $result_fields = array_merge(['name', 'division_name', 'salary', 'cs_adress_intr', 'max_salary'], $fields);
        $params = [
            'vacancy' => [
                'id' => $id,
            ],
            'field_names' => $result_fields,
        ];

        $data = $this->client->post('vacancy/get', $params);
        Log::channel('estaff')->info(__FUNCTION__.' get', ['rid' => $rid, 'id' => $id]);

        return $data;
    }

    public function getCandidate(int $id, array $fields = []): array
    {
        $rid = $this->newRid();
        Log::channel('estaff')->info(__FUNCTION__.' send', ['rid' => $rid, 'id' => $id]);
        $result_fields = array_merge(['mobile_phone'], $fields);
        $params = [
            'candidate' => [
                'id' => $id,
            ],
            'field_names' => $result_fields,
        ];

        $data = $this->client->post('candidate/get', $params);
        Log::channel('estaff')->info(__FUNCTION__.' get', ['rid' => $rid, 'id' => $id]);

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

    /*
    |--------------------------------------------------------------------------
    | New flow (config/flow.php)
    |--------------------------------------------------------------------------
    */

    /**
     * Candidates sharing a mobile phone (ТЗ 3.1). Returns the `candidates` list in API order.
     */
    public function findCandidatesByPhone(string $phone): array
    {
        $data = $this->call(__FUNCTION__, 'candidate/find', [
            'filter' => ['mobile_phone' => $phone],
            'field_names' => ['id', 'main_vacancy_id'],
        ]);

        return is_array($data['candidates'] ?? null) ? $data['candidates'] : [];
    }

    /**
     * position_id («Штатная должность») of a vacancy, null when absent.
     */
    public function getVacancyPositionId(int $vacancyId): ?string
    {
        $data = $this->getVacancy($vacancyId, ['position_id']);
        $position = $data['vacancy']['position_id'] ?? null;

        return $position === null || $position === '' ? null : (string) $position;
    }

    /**
     * Current state of a candidate and the date of that state's event (ТЗ 6.4).
     * State field is `state_id`. The date comes from the latest `events[]` entry matching the state
     * (`type_id[:occurrence_id]`, e.g. event_type_49 + scheduled) — for a lead that is the interview date.
     * Estaff `state_date` is only the transition date, so a dedicated date field is used only when configured.
     *
     * @return array{state: string|null, state_date: string|null}
     */
    public function getCandidateState(int $id): array
    {
        $fields = config('flow.estaff_state_fields', []);
        $stateField = $fields['state'] ?? 'state_id';
        $dateField = (string) ($fields['state_date'] ?? '');

        $data = $this->getCandidate($id, array_values(array_unique(array_filter([$stateField, $dateField, 'events']))));
        $candidate = $data['candidate'] ?? [];

        $state = isset($candidate[$stateField]) && $candidate[$stateField] !== '' ? (string) $candidate[$stateField] : null;
        $stateDate = null;

        if ($state !== null && is_array($candidate['events'] ?? null)) {
            $stateDate = $this->latestEventDate($candidate['events'], $state);
        }

        if ($stateDate === null && $dateField !== '' && isset($candidate[$dateField]) && $candidate[$dateField] !== '') {
            $stateDate = (string) $candidate[$dateField];
        }

        return ['state' => $state, 'state_date' => $stateDate];
    }

    /**
     * Date of the most recent event whose type (and occurrence, when the state has a ":suffix") matches the state.
     */
    private function latestEventDate(array $events, string $state): ?string
    {
        [$typeId, $occurrence] = array_pad(explode(':', $state, 2), 2, null);
        $latest = null;

        foreach ($events as $event) {
            if (! is_array($event) || ($event['type_id'] ?? null) !== $typeId || empty($event['date'])) {
                continue;
            }
            if ($occurrence !== null && isset($event['occurrence_id']) && (string) $event['occurrence_id'] !== $occurrence) {
                continue;
            }
            if ($latest === null || strcmp((string) $event['date'], $latest) > 0) {
                $latest = (string) $event['date'];
            }
        }

        return $latest;
    }

    /**
     * Fill location_id on a candidate card (ТЗ 1). `changed_data.location_id` is accepted by Estaff
     * although the public docs list only user_login / vacancy_id / drop_other_vacancies for candidate/change.
     */
    public function updateCandidateLocation(int $id, string $locationId): array
    {
        return $this->changeCandidate([
            'candidate' => ['id' => $id],
            'changed_data' => ['location_id' => $locationId],
        ]);
    }

    public function setWebhook(array $params): array
    {
        return $this->call(__FUNCTION__, 'webhook/set', $params);
    }

    public function getWebhooks(): array
    {
        $rid = $this->newRid();
        Log::channel('estaff')->info(__FUNCTION__.' send', ['rid' => $rid]);
        $data = $this->client->post('webhook/get', []);
        Log::channel('estaff')->info(__FUNCTION__.' get', ['rid' => $rid] + $data);

        return $data;
    }

    public function deleteWebhook(string $id): array
    {
        return $this->call(__FUNCTION__, 'webhook/delete', ['id' => $id]);
    }

    public function ping(): array
    {
        $url = rtrim($this->config['url'], '/').'/openapi.html';
        $transferStats = null;
        $httpCode = 0;
        $error = null;

        $client = app('GuzzleClient')(['timeout' => 10]);

        try {
            $response = $client->request('GET', $url, [
                'http_errors' => false,
                'verify' => false,
                'on_stats' => function (TransferStats $stats) use (&$transferStats) {
                    $transferStats = $stats;
                },
            ]);
            $httpCode = $response->getStatusCode();
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $handlerStats = $transferStats ? $transferStats->getHandlerStats() : [];

        return [
            'success' => $httpCode > 0 && $error === null,
            'http_code' => $httpCode,
            'connect_ms' => round(($handlerStats['connect_time'] ?? 0) * 1000),
            'total_ms' => round(($transferStats ? $transferStats->getTransferTime() : 0) * 1000),
            'error' => $error,
        ];
    }
}
