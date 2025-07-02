<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstaffWebhook;
use App\Jobs\StartTwinManualConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class WebhookController extends Controller
{
    public function estaffWebhooks(EstaffWebhook $request)
    {
        $data = $request->all();
        Log::channel('estaff')->info("Webhook received", );

        if ($data['event_type'] === 'candidate_state' && $data['data']['state_id'] === 'event_type_32') {
            dispatch(new StartTwinManualConversation($data['data']['vacancy_id'], $data['data']['candidate_id']));
        }

        return response()->json('ok', 200);
    }

    public function twinWebhooks(Request $request)
    {
        Log::channel('twin')->info("Webhook received", $request->all());



        return response()->json('ok', 200);
    }
}
