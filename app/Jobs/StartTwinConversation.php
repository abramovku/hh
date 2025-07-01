<?php

namespace App\Jobs;

use App\Models\Response;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StartTwinConversation implements ShouldQueue
{
    use Queueable;

    private int $id;
    /**
     * Create a new job instance.
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $EstaffService = app('estaff');

        $candidate = Response::findOrFail($this->id);

    }
}
