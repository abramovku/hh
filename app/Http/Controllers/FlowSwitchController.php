<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstaffWebhook;
use App\Http\Requests\Flow\CallEndedWebhook;
use App\Services\Estaff\WebhookDeduplicator;
use App\Services\Flow\FlowRouter;
use App\Support\Flow;
use Illuminate\Support\Facades\Log;

/**
 * Entry point for Estaff / Twin voice webhooks in every flow mode (config('flow.mode')).
 * Validation happens here, the legacy/new decision is made by FlowRouter.
 */
class FlowSwitchController extends Controller
{
    public function estaffWebhooks(EstaffWebhook $request, FlowRouter $router, WebhookDeduplicator $dedup)
    {
        $data = $request->all();
        Log::channel('estaff')->info('Webhook received', ['mode' => Flow::mode()] + $data);

        // Estaff repeats the same candidate_state webhook; only the first copy within the TTL is processed.
        if ($dedup->isDuplicate($data)) {
            Log::channel('estaff')->warning('Webhook ignored: duplicate', [
                'candidate_id' => $data['data']['candidate_id'] ?? null,
                'state_id' => $data['data']['state_id'] ?? null,
                'vacancy_id' => $data['data']['vacancy_id'] ?? null,
                'first_received_at' => $dedup->firstSeenAt($data),
                'ttl' => $dedup->ttl(),
            ]);

            return response()->json('ok', 200);
        }

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
