<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HHAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:hh-auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate OAuth url to complete the Authentication process.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $auth_url = config('services.hh.api_auth_url');
        $client_id = config('services.hh.client_id');
        $redirect_url = config('services.hh.redirect_uri');
        $url = $auth_url . '?response_type=code&client_id=' . $client_id . '&redirect_uri=' .
            $redirect_url;
        $this->info('Copy the following url, past on browser and hit return.');
        $this->line($url);
    }
}
