<?php

namespace App\Services\HH;

use Illuminate\Support\Facades\Log;

class HH
{
    private $HHClient;
    private $config;
    public function __construct($config)
    {
        $this->config = $config;
        $this->HHClient = new HHClient($config);
    }

    public function baseInstall(string $code)
    {
        if (!empty($code)) $this->HHClient->auth($code);
    }

    public function getMe(): array
    {
        Log::channel('hh')->info(__FUNCTION__ . ' send');
        $data = $this->HHClient->get('/me');
        Log::channel('hh')->info(__FUNCTION__ . ' get');
        return $data;
    }

    public function getManagers(): array
    {
        Log::channel('hh')->info(__FUNCTION__ . ' send');
        $result = [];
        $data = $this->HHClient->get('/employers/' . $this->config['employer'] . '/managers');
        if (!empty($data['items'])) {
            $result = $data['items'];
        }

        if ($data['pages'] > 1) {
            for ($i = 1; $i <= $data['pages'] - 1; $i++) {
                $dataNew = $this->HHClient->get('/employers/' . $this->config['employer'] . '/managers?page=' . $i);
                if (!empty($dataNew['items'])) {
                    $result = array_merge($result, $dataNew['items']);
                }
            }
        }

        Log::channel('hh')->info(__FUNCTION__ . ' get');
        return $result;
    }

    public function getVacanciesByManager(int $id): array
    {
        Log::channel('hh')->info(__FUNCTION__ . ' send', ['id' => $id]);
        $result = [];
        $data = $this->HHClient->get('/employers/' . $this->config['employer']
            . '/vacancies/active?manager_id=' . $id);
        if (!empty($data['items'])) {
            $result = $data['items'];
        }

        if ($data['pages'] > 1) {
            for ($i = 1; $i <= $data['pages'] - 1; $i++) {
                $dataNew = $this->HHClient->get('/employers/' . $this->config['employer']
                    . '/vacancies/active?manager_id=' . $id . '&page=' . $i);
                if (!empty($dataNew['items'])) {
                    $result = array_merge($result, $dataNew['items']);
                }
            }
        }
        Log::channel('hh')->info(__FUNCTION__ . ' get');
        return $result;
    }

    public function getResponcesByVacancy(int $id): array
    {
        Log::channel('hh')->info(__FUNCTION__ . ' send', ['id' => $id]);
        $result = [];
        $data = $this->HHClient->get('/negotiations/response?vacancy_id=' . $id);
        if (!empty($data['items'])) {
            $result = $data['items'];
        }

        if ($data['pages'] > 1) {
            for ($i = 1; $i <= $data['pages'] - 1; $i++) {
                $dataNew = $this->HHClient->get('/negotiations/response?vacancy_id=' . $id . '&page=' . $i);
                if (!empty($dataNew['items'])) {
                    $result = array_merge($result, $dataNew['items']);
                }
            }
        }
        Log::channel('hh')->info(__FUNCTION__ . ' get');
        return $result;
    }

    public function getResume(string $id, int $response_id, int $vacancy_id): array
    {
        Log::channel('hh')->info(__FUNCTION__ . ' send', ['id' => $id, 'response_id' => $response_id,
            'vacancy_id' => $vacancy_id]);
        $data = $this->HHClient->get('/resumes/' . $id . '?topic_id=' . $response_id . '&vacancy_id=' . $vacancy_id);
        Log::channel('hh')->info(__FUNCTION__ . ' get');
        return $data;
    }
}
