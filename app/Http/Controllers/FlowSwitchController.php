<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstaffWebhook;
use App\Http\Requests\Flow\CallEndedWebhook;
use App\Services\Flow\FlowRouter;
use App\Support\Flow;
use Illuminate\Support\Facades\Log;

/**
 * Entry point for Estaff / Twin voice webhooks in every flow mode (config('flow.mode')).
 * Validation happens here, the legacy/new decision is made by FlowRouter.
 */
class FlowSwitchController extends Controller
{
    public function estaffWebhooks(EstaffWebhook $request, FlowRouter $router)
    {
        $data = $request->all();
        Log::channel('estaff')->info('Webhook received', ['mode' => Flow::mode()] + $data);

        $router->routeEstaffState($data);

        return response()->json('ok', 200);
    }

    public function twinVoiceWebhooks(FlowRouter $router)
    {
        if (Flow::isLegacy()) {
            // Keep the strict legacy validation (event must be CANDIDATE_CHANGED).
            return app()->call([app(WebhookController::class), 'twinVoiceWebhooks']);
        }

        $data = app(CallEndedWebhook::class)->all();
        Log::channel('twin')->info('Webhook voice received', ['mode' => Flow::mode()] + $data);

        $router->routeVoice($data);

        return response()->json('ok', 200);
    }
}
