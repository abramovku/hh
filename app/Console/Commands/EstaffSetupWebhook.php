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
        $this->info("Webhook handler url: " . route('estaff.webhook'));
        $action = $this->choice('Select action with Estaff webhook', ['add', 'get', 'delete']);
        switch ($action) {
            case 'add':
                $data = ["url" => route('estaff.webhook'),
                    "name" => "Candidate states webhook",
                    "events" => ["candidate_state"]];
                $response = $EstaffService->setWebhook($data);
                dd($response);
                break;
            case 'get':
                $response = $EstaffService->getWebhooks();
                dd($response);
                break;
            case 'delete':
                $id = $this->ask('Enter webhook id');
                $response = $EstaffService->deleteWebhook($id);
                dd($response);
        }
    }
}
