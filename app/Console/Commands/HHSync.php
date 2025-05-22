<?php

namespace App\Console\Commands;

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
                        $responses = $hhService->getResponcesByVacancy($vacancy['id']);
                        foreach ($responses['items'] as $item) {
                            $response = Response::where('response_id', $item['id'])->first();
                            if ($response === null) {
                                $response = Response::create([
                                    'response_id' => $item['id'],
                                    'manager_id' => $manager['id'],
                                    'vacancy_id' => $vacancy['id']
                                ]);
                                $resume = $item['resume'];

                                if (!empty($resume['last_name']) ||
                                    !empty($resume['first_name']) ||
                                    !empty($resume['middle_name'])) {
                                    $fio = '';
                                    if (!empty($resume['last_name'])) {
                                        $fio .= $resume['last_name'] . ' ';
                                    }
                                    if (!empty($resume['first_name'])) {
                                        $fio .= $resume['first_name'] . ' ';
                                    }
                                    if (!empty($resume['middle_name'])) {
                                        $fio .= $resume['middle_name'];
                                    }
                                    $response->meta()->create(
                                        ['key' => 'fio', 'value' => $fio]
                                    );
                                }

                                if (!empty($resume['age'])) {
                                    $response->meta()->create(
                                        ['key' => 'age', 'value' => $resume['age']]
                                    );
                                }

                                if (!empty($resume['gender']['id'])) {
                                    $response->meta()->create(
                                        ['key' => 'gender', 'value' => $resume['gender']['id']]
                                    );
                                }

                                if (!empty($resume['title'])) {
                                    $response->meta()->create(
                                        ['key' => 'title', 'value' => $resume['title']]
                                    );
                                }

                                if (!empty($resume['area']['title'])) {
                                    $response->meta()->create(
                                        ['key' => 'location', 'value' => $resume['area']['title']]
                                    );
                                }

                                if (!empty($resume['id'])) {
                                    $response->meta()->create(
                                        ['key' => 'resume_id', 'value' => $resume['id']]
                                    );

                                    $fullResume = $hhService->getResume($resume['id'], $item['id'], $vacancy['id']);

                                    if (!empty($fullResume['citizenship']['name'])) {
                                        $response->meta()->create(
                                            ['key' => 'citizenship', 'value' => $fullResume['citizenship']['name']]
                                        );
                                    }

                                    if (!empty($fullResume['contact']) &&
                                        is_array($fullResume['contact'])
                                    ) {
                                        foreach ($fullResume['contact'] as $contact) {
                                            $type = $contact['type']['id'];
                                            if ($type === 'cell') {
                                                if (!empty($contact['value']['formatted'])){
                                                    $response->meta()->create(
                                                        ['key' => $type, 'value' => $contact['value']['formatted']]
                                                    );
                                                }
                                            } elseif ($type === 'email') {
                                                if (!empty($contact['value'])) {
                                                    $response->meta()->create(
                                                        ['key' => $type, 'value' => $contact['value']]
                                                    );
                                                }
                                            }
                                        }
                                    }

                                    if (!empty($fullResume['education'])) {
                                        $response->meta()->create(
                                            ['key' => 'education', 'value' => json_encode($fullResume['education'])]
                                        );
                                    }

                                    if (!empty($fullResume['experience'])) {
                                        $response->meta()->create(
                                            ['key' => 'experience', 'value' => json_encode($fullResume['experience'])]
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
