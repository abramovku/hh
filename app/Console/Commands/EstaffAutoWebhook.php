<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EstaffAutoWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:estaff-auto-webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup webhook for candidate events if it not exist';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $EstaffService = app('estaff');

        $data = $EstaffService->getWebhooks();

        if (empty($data['webhooks'][0]['id'])) {
            Log::channel('app')->info("Webhook is empty need to recreate");
            $data = ["url" => route('estaff.webhook'),
                "name" => "Candidate states webhook",
                "events" => ["candidate_state"]];
            $EstaffService->setWebhook($data);
            return;
        }

        Log::channel('app')->info("Webhook is exists " . $data['webhooks'][0]['id'] );
    }
}
