<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EstaffSetupWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:estaff-setup-webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup webhook for candidate events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $EstaffService = app('estaff');
        $data = [
            "url" => route('estaff.wehook'),
            "name" => "Candidate states webhook",
            "events" => [
                "candidate_state"
            ]
        ];
        $response = $EstaffService->setWebhook($data);
        $this->info($response);

    }
}
