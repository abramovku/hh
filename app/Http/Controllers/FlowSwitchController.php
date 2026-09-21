<?php

namespace App\Http\Controllers;

use App\Support\Flow;

/**
 * Routes Estaff / Twin voice webhooks to the legacy or the new flow controller
 * depending on config('flow.mode'). The target FormRequest is resolved from the
 * container, so each controller keeps its own validation.
 */
class FlowSwitchController extends Controller
{
    public function estaffWebhooks()
    {
        return $this->forward(__FUNCTION__);
    }

    public function twinVoiceWebhooks()
    {
        return $this->forward(__FUNCTION__);
    }

    private function forward(string $method)
    {
        $controller = Flow::isNew() ? FlowWebhookController::class : WebhookController::class;

        return app()->call([app($controller), $method]);
    }
}
