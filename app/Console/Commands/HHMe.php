<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HHMe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:hh-me';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get info about HH account';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info(app('hh')->getMe());
    }
}
