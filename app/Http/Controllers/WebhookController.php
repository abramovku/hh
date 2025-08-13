<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstaffWebhook;
use App\Http\Requests\TwinTextWebhook;
use App\Http\Requests\TwinVoiceWebhook;
use App\Jobs\OperateTwinVoiceWebhook;
use App\Jobs\OperateTwinWebhook;
use App\Jobs\StartTwinCall;
use App\Jobs\StartTwinColdConversation;
use App\Jobs\StartTwinManualConversation;
use App\Jobs\StartTwinSms;
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
                    if (!empty($data['data']['vacancy_id']) && !empty($data['data']['candidate_id'])) {
                        dispatch(new StartTwinManualConversation($data['data']['vacancy_id'], $data['data']['candidate_id']));
                    }
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
                case 'event_type_48':
                    if (!empty($data['data']['candidate_id'])) {
                        dispatch(new StartTwinColdConversation($data['data']['candidate_id']));
                    }
                    break;
            }
        }

        return response()->json('ok', 200);
    }

    public function twinWebhooks(TwinTextWebhook $request)
    {
        $data = $request->all();

        Log::channel('twin')->info("Webhook received", $data);

        dispatch(new OperateTwinWebhook($data));

        return response()->json('ok', 200);
    }

    public function twinVoiceWebhooks(TwinVoiceWebhook $request)
    {
        $data = $request->all();

        Log::channel('twin')->info("Webhook voice received", $data);

        dispatch(new OperateTwinVoiceWebhook($data));

        return response()->json('ok', 200);
    }
}
