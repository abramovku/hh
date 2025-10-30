<?php

namespace App\Console\Commands;

use App\Jobs\StartTwinConversation;
use App\Models\Response;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EstaffSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:estaff-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync estaff data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $EstaffService = app('estaff');

        $data = Response::whereNull('sent_at')->get()->groupBy('vacancy_id');
        if (empty($data)) {
            $this->info('there is no responses to send!');
            return;
        }

        foreach ($data as $vacancy => $responses) {
            $vacancyData = $EstaffService->findVacancy($vacancy);
            if (empty($vacancyData)) {
                $this->info("vacancy {$vacancy} not found in estaff");
                Log::channel('estaff')->info("sync vacancy not found in estaff", [
                    'vacancy' => $vacancy,
                ]);
                foreach ($responses as $candidate) {
                    $candidate->setSend();
                    $candidate->save();
                }
                continue;
            }
            foreach ($responses as $candidate) {
                $EstaffCadidate = $EstaffService->addResponse($this->prepareCandidate($candidate, $vacancyData));
                $candidate->candidate_estaff = $EstaffCadidate['candidate']['id'];
                $candidate->vacancy_estaff = $vacancyData['id'];
                $candidate->setSend();
                $candidate->save();

                dispatch(new StartTwinConversation($candidate->id));
            }
        }
    }

    private function prepareCandidate(Response $candidate, array $vacancy): array
    {
        $last_name = $candidate->meta()->where('key', 'last_name')->first();
        $middle_name = $candidate->meta()->where('key', 'middle_name')->first();
        $gender = $candidate->meta()->where('key', 'gender')->first();
        $birth_date = $candidate->meta()->where('key', 'birth_date')->first();
        $cell = $candidate->meta()->where('key', 'cell')->first();
        $email = $candidate->meta()->where('key', 'email')->first();
        $title = $candidate->meta()->where('key', 'title')->first();
        $edu = $candidate->meta()->where('key', 'education')->first();
        $exp = $candidate->meta()->where('key', 'experience')->first();

        $data = [];
        $data['candidate']['firstname'] = $candidate->meta()->where('key', 'first_name')
            ->first()->value;

        $data['candidate']['user_id'] = $vacancy['user_id'];

        $data['candidate']['entrance_type_id'] = "vacancy_response";
        $data['candidate']['source_id'] = "hh.ru";

        if (!empty(optional($last_name)->value)) {
            $data['candidate']['lastname'] = $last_name->value;
        }

        if (!empty(optional($middle_name)->value)) {
            $data['candidate']['middlename'] = $middle_name->value;
        }

        if (!empty(optional($gender)->value)) {
            $gender_id = 0;
            if ($gender->value === 'female') {
                $gender_id = 1;
            }
            $data['candidate']['gender_id'] = $gender_id;
        }

        if (!empty(optional($birth_date)->value)) {
            $data['candidate']['birth_date'] = $birth_date->value;
        }


        if (!empty(optional($cell)->value)) {
            $data['candidate']['mobile_phone'] = $cell->value;
        }

        if (!empty(optional($email)->value)) {
            $data['candidate']['email'] = $email->value;
        }

        if (!empty(optional($title)->value)) {
            $data['candidate']['desired_position_name'] = $title->value;
        }

        if (!empty(optional($edu)->value)) {
            $edu_data = json_decode($edu->value, true);
            if (!empty($edu_data['primary']) && is_array($edu_data['primary'])) {
                foreach ($edu_data['primary'] as $key =>$edu_item) {
                    if (!empty($edu_item['name'])) {
                        $data['candidate']['prev_educations'][$key]['org_name'] = $edu_item['name'];
                    }

                    if (!empty($edu_item['result'])) {
                        $data['candidate']['prev_educations'][$key]['speciality_name'] = $edu_item['result'];
                    }

                    if (!empty($edu_item['year'])) {
                        $data['candidate']['prev_educations'][$key]['end_year'] = intval($edu_item['year']);
                    }
                }
            }
        }

        if (!empty(optional($exp)->value)) {
            $exp_data = json_decode($exp->value, true);
            if (!empty($exp_data) && is_array($exp_data)) {
                foreach ($exp_data as $key =>$exp_item) {
                    if (!empty($exp_item['company'])) {
                        $data['candidate']['prev_jobs'][$key]['org_name'] = $exp_item['company'];
                    } else {
                        $data['candidate']['prev_jobs'][$key]['org_name'] = 'unknown';
                    }

                    if (!empty($exp_item['position'])) {
                        $data['candidate']['prev_jobs'][$key]['position_name'] = $exp_item['position'];
                    } else {
                        $data['candidate']['prev_jobs'][$key]['position_name'] = 'unknown';
                    }

                    if (!empty($exp_item['description'])) {
                        $data['candidate']['prev_jobs'][$key]['comment'] = $exp_item['description'];
                    }

                    if (!empty($exp_item['start'])) {
                        $startObj = Carbon::parse($exp_item['start']);
                        $data['candidate']['prev_jobs'][$key]['start_year'] = $startObj->year;
                        $data['candidate']['prev_jobs'][$key]['start_month'] = $startObj->month;
                    }

                    if (!empty($exp_item['end'])) {
                        $endObj = Carbon::parse($exp_item['end']);
                        $data['candidate']['prev_jobs'][$key]['end_year'] = $endObj->year;
                        $data['candidate']['prev_jobs'][$key]['end_month'] = $endObj->month;
                    }
                }
            }
        }

        $data['vacancy']['id'] = $vacancy['id'];
        return $data;
    }
}
