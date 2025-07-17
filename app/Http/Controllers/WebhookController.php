<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstaffWebhook;
use App\Http\Requests\TwinTextWebhook;
use App\Jobs\StartTwinCall;
use App\Jobs\StartTwinManualConversation;
use App\Models\TwinTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class WebhookController extends Controller
{
    public function estaffWebhooks(EstaffWebhook $request)
    {
        $data = $request->all();
        Log::channel('estaff')->info("Webhook received", $data);

        if ($data['event_type'] === 'candidate_state' && !empty($data['data']['state_id'])) {
            switch ($data['data']['state_id']) {
                case 'event_type_32':
                    dispatch(new StartTwinManualConversation($data['data']['vacancy_id'], $data['data']['candidate_id']));
                    break;
                case 'event_type_47':
                    $task = TwinTask::where('candidate_id', $data['data']['candidate_id'])->first();
                    if (!empty($task)) {
                        DB::table('jobs')->where('id', $task->job_id)->delete();
                        Log::channel('twin')->info("task remove from queue", ["task" => $task->id]);
                    }
                    dispatch(new StartTwinCall($data['data']['candidate_id']));
                    break;
                case 'event_type_44':
                    dispatch(new StartTwinSms($data['data']['candidate_id']));
                    break;
            }
        }

        return response()->json('ok', 200);
    }

    public function twinWebhooks(TwinTextWebhook $request)
    {
        $data = $request->all();
        $flowStatuses = ['PENDING', 'DELAYED', 'UNDELIVERED', 'ERROR', 'PAUSED'];
        Log::channel('twin')->info("Webhook received", $data);

        $task = TwinTask::where('candidate_id', intval($data['callbackData']))->first();

        if (!in_array($data['newStatus'], $flowStatuses)) {
            Log::channel('twin')->info("task can be removed from queue", $data);
            if (!empty($task)) {
                DB::table('jobs')->where('id', $task->job_id)->delete();
                Log::channel('twin')->info("task removed from queue", ["task" => $task->id]);
                return response()->json('ok', 200);
            }
        }


        $job = dispatch(new StartTwinCall(intval($data['callbackData'])))->delay(now()->addHours(4));

        $jobId = Queue::push($job);

        $newTask = new TwinTask();
        $newTask->chat_id = $data['id'];
        $newTask->candidate_id = $data['callbackData'];
        $newTask->job_id = $jobId;
        $newTask->save();

        Log::channel('twin')->info("task added to queue", ["task" => $task->id]);

        return response()->json('ok', 200);
    }
}
