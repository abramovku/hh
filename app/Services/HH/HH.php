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
        $data = $this->HHClient->get('/employers/' . $this->config['employer'] . '/managers');
        Log::channel('hh')->info(__FUNCTION__ . ' get');
        return $data;
    }

    public function getVacanciesByManager(int $id): array
    {
        Log::channel('hh')->info(__FUNCTION__ . ' send');
        $data = $this->HHClient->get('/employers/' . $this->config['employer']
            . '/vacancies/active?manager_id=' . $id);
        Log::channel('hh')->info(__FUNCTION__ . ' get');
        return $data;
    }

    public function getResponcesByVacancy(int $id): array
    {
        Log::channel('hh')->info(__FUNCTION__ . ' send');
        $data = $this->HHClient->get('/negotiations/response?vacancy_id=' . $id);
        Log::channel('hh')->info(__FUNCTION__ . ' get');
        return $data;
    }
}
