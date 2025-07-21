<?php

namespace App\Jobs;

use App\Models\TwinTask;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;

class OperateTwinWebhook implements ShouldQueue
{
    use Dispatchable, Queueable;

    private array $data;
    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $flowStatuses = ['PENDING', 'DELAYED', 'UNDELIVERED', 'ERROR', 'PAUSED'];

        $task = TwinTask::where('candidate_id', intval($this->data['callbackData']))->first();

        if (!in_array($this->data['newStatus'], $flowStatuses)) {
            Log::channel('twin')->info("task can be removed from queue", $this->data);
            if (!empty($task)) {
                DB::table('jobs')->where('id', $task->job_id)->delete();
                Log::channel('twin')->info("task removed from queue", ["task" => $task->id]);
                return;
            }
        }

        $delay = now()->addHours(4);
        $job = new StartTwinCall(intval($this->data['callbackData']));


        $jobId = DB::table('jobs')->insertGetId([
            'queue' => 'default',
            'payload' => Queue::createPayload($job),
            'attempts' => 0,
            'reserved' => 0,
            'available_at' => $delay->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $newTask = new TwinTask();
        $newTask->chat_id = $this->data['id'];
        $newTask->candidate_id = $this->data['callbackData'];
        $newTask->job_id = $jobId;
        $newTask->save();

        Log::channel('twin')->info("task added to queue", ["task" => $task->id ?? 0]);
    }
}
