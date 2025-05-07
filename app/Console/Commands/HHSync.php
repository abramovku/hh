<?php

namespace App\Console\Commands;

use App\Models\Manager;
use App\Models\Response;
use Illuminate\Console\Command;

class HHSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:hh-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sync hh data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hhService = app('hh');
        $managers = $hhService->getManagers();

        if (!empty($managers['items'])) {
            foreach ($managers['items'] as $manager) {
                if ($manager['vacancies_count'] === 0) {
                    continue;
                }
                $vacancies = $hhService->getVacanciesByManager($manager['id']);
                if (!empty($vacancies['items'])) {
                    foreach ($vacancies['items'] as $vacancy) {
                        $responces = $hhService->getResponcesByVacancy($vacancy['id']);
                        foreach ($responces['items'] as $responce) {
                            Response::firstOrCreate(['response_id' => $responce['id']], ['email' => $manager['email']]);
                        }
                    }
                }
            }
        }


    }


}
