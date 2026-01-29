<?php

namespace App\Console\Commands;

use App\Models\Response;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

    private const RESUME_COUNT = 5;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hhService = app('hh');
        $EstaffService = app('estaff');
        $managers = $hhService->getManagers();
        $loaded = 0;

        if (!empty($managers)) {
            foreach ($managers as $manager) {
                if ($manager['vacancies_count'] === 0 || $manager['vacancies_count'] === null ) {
                    continue;
                }
                $vacancies = $hhService->getVacanciesByManager($manager['id']);
                if (!empty($vacancies)) {
                    foreach ($vacancies as $vacancy) {
                        // Проверяем наличие вакансии в estaff
                        if (empty($EstaffService->findVacancy($vacancy['id']))) {
                            Log::channel('hh')->info("vacancy not found in estaff", ['vacancy' => $vacancy['id']]);
                            continue;
                        }

                        $responses = $hhService->getResponcesByVacancy($vacancy['id']);
                        foreach ($responses as $item) {
                            $response = Response::where('response_id', $item['id'])->first();
                            if ($response === null) {
                                $resume = $item['resume'];

                                if (empty($resume['first_name'])) {
                                    Log::channel('hh')->info("response haven't first name", [
                                        'vacancy' => $vacancy['id'],
                                        'response' => $item['id'],
                                    ]);
                                    $response = Response::create([
                                        'response_id' => $item['id'],
                                        'manager_id' => $manager['id'],
                                        'vacancy_id' => $vacancy['id']
                                    ]);
                                    $response->error = "response haven't first name";
                                    $response->save();
                                    continue;
                                }

                                if (empty($resume['id'])) {
                                    Log::channel('hh')->info("response haven't resume id", [
                                        'vacancy' => $vacancy['id'],
                                        'response' => $item['id'],
                                    ]);
                                    $response = Response::create([
                                        'response_id' => $item['id'],
                                        'manager_id' => $manager['id'],
                                        'vacancy_id' => $vacancy['id']
                                    ]);
                                    $response->error = "response haven't resume id";
                                    $response->save();
                                    continue;
                                }

                                // Ограничение на количество обрабатываемых резюме за один запуск
                                $loaded++;
                                if ($loaded > self::RESUME_COUNT) {
                                    $this->info('Load limit reached, stopping execution.');
                                    return;
                                }

                                $response = Response::create([
                                    'response_id' => $item['id'],
                                    'manager_id' => $manager['id'],
                                    'vacancy_id' => $vacancy['id']
                                ]);

                                try {
                                    $fullResume = $hhService->getResume($resume['id'], $item['id'], $vacancy['id']);
                                } catch (\Exception $e) {
                                    Log::channel('hh')->info("response full resume error", ['error' =>
                                        $e->getMessage()]);
                                    $response->error = substr($e->getMessage(), 0, 255);
                                    $response->save();
                                }

                                if (empty($fullResume)) {
                                    Log::channel('hh')->info("response full resume empty", [
                                        'vacancy' => $vacancy['id'],
                                        'response' => $item['id'],
                                        'resume' => $resume['id']
                                    ]);
                                    $response->error = "response full resume empty";
                                    $response->save();
                                    continue;
                                }

                                $email = $cell = '';

                                if (!empty($fullResume['contact']) &&
                                    is_array($fullResume['contact'])
                                ) {
                                    foreach ($fullResume['contact'] as $contact) {
                                        $type = $contact['type']['id'];
                                        if ($type === 'cell') {
                                            if (!empty($contact['value']['formatted'])){
                                                $cell = $contact['value']['formatted'];
                                            }
                                        } elseif ($type === 'email') {
                                            if (!empty($contact['value'])) {
                                                $email = $contact['value'];
                                            }
                                        }
                                    }
                                }

                                if (empty($email) && empty($cell)) {
                                    Log::channel('hh')->info("response haven't email and cell", [
                                        'vacancy' => $vacancy['id'],
                                        'response' => $item['id'],
                                    ]);
                                    $response->error = "response haven't email and cell";
                                    $response->save();
                                    continue;
                                }

                                $response->meta()->create(
                                    ['key' => 'first_name', 'value' => $resume['first_name']]
                                );

                                if (!empty($resume['last_name'])) {
                                    $response->meta()->create(
                                        ['key' => 'last_name', 'value' => $resume['last_name']]
                                    );
                                }

                                if (!empty($resume['middle_name'])) {
                                    $response->meta()->create(
                                        ['key' => 'middle_name', 'value' => $resume['middle_name']]
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

                                if (!empty($fullResume['area']['title'])) {
                                    $response->meta()->create(
                                        ['key' => 'location', 'value' => $fullResume['area']['title']]
                                    );
                                }

                                $response->meta()->create(
                                    ['key' => 'resume_id', 'value' => $resume['id']]
                                );


                                if (!empty($fullResume['citizenship']['name'])) {
                                    $response->meta()->create(
                                        ['key' => 'citizenship', 'value' => $fullResume['citizenship']['name']]
                                    );
                                }

                                if (!empty($fullResume['birth_date'])) {
                                    $response->meta()->create(
                                        ['key' => 'birth_date', 'value' => $fullResume['birth_date']]
                                    );
                                }

                                if (!empty($cell)) {
                                    $response->meta()->create(
                                        ['key' => 'cell', 'value' => $cell]
                                    );
                                }

                                if (!empty($email)) {
                                    $response->meta()->create(
                                        ['key' => 'email', 'value' => $email]
                                    );
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
