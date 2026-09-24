<?php

namespace App\Services\Flow;

use App\Http\Controllers\FlowWebhookController;
use App\Http\Controllers\WebhookController;
use App\Jobs\Flow\ProcessCallEnded;
use App\Jobs\Flow\ResolveVacancyAndRoute;
use App\Jobs\OperateTwinVoiceWebhook;
use App\Support\Flow;
use Illuminate\Support\Facades\Log;

/**
 * The single place that decides whether a webhook is handled by the legacy or the new flow.
 *
 *  legacy → old handlers; new → new handlers;
 *  hybrid → by Estaff vacancy id (config flow.new_vacancy_ids) for candidate states,
 *           by Twin bot / autoCall id for call webhooks.
 */
class FlowRouter
{
    /** Estaff states the new flow reacts to; legacy handles 47/48 as well, so these need the vacancy check. */
    public const NEW_FLOW_STATES = ['new', 'event_type_47', 'event_type_48'];

    /**
     * @param  int|null  $resolvedVacancyId  Vacancy id resolved from Estaff by ResolveVacancyAndRoute (0 = candidate has none).
     */
    public function routeEstaffState(array $data, ?int $resolvedVacancyId = null): void
    {
        if (Flow::isNew()) {
            $this->newFlow()->handleState($data);

            return;
        }

        if (Flow::isLegacy()) {
            $this->legacy()->handleState($data);

            return;
        }

        $stateId = (string) ($data['data']['state_id'] ?? '');
        $candidateId = (int) ($data['data']['candidate_id'] ?? 0);
        $isCandidateState = ($data['event_type'] ?? null) === 'candidate_state' && $candidateId > 0 && $stateId !== '';

        if (! $isCandidateState || ! in_array($stateId, self::NEW_FLOW_STATES, true)) {
            // Legacy-only or unknown states. Interview schedules exist only for new-flow candidates,
            // so cancelling them here never touches legacy data (ТЗ 6.4).
            $this->legacy()->handleState($data);
            if ($isCandidateState) {
                $this->newFlow()->cancelSchedulesOnStateChange($candidateId, $stateId);
            }

            return;
        }

        $vacancyId = $resolvedVacancyId ?? (! empty($data['data']['vacancy_id']) ? (int) $data['data']['vacancy_id'] : null);

        if ($vacancyId === null) {
            Log::channel('estaff')->info('hybrid: webhook has no vacancy_id, resolving from candidate', [
                'candidate_id' => $candidateId, 'state_id' => $stateId,
            ]);
            dispatch(new ResolveVacancyAndRoute($data));

            return;
        }

        $useNew = Flow::usesNewFlow($vacancyId);
        Log::channel('estaff')->info('hybrid: candidate state routed', [
            'candidate_id' => $candidateId, 'state_id' => $stateId, 'vacancy_id' => $vacancyId, 'flow' => $useNew ? 'new' : 'legacy',
        ]);

        $useNew ? $this->newFlow()->handleState($data) : $this->legacy()->handleState($data);
    }

    public function routeVoice(array $data): void
    {
        $event = $data['event'] ?? null;

        if (Flow::isLegacy()) {
            dispatch(new OperateTwinVoiceWebhook($data));

            return;
        }

        $isNewCall = Flow::isNew() || Flow::isNewFlowCall($data['botId'] ?? null, [$data['taskId'] ?? null, $data['autoCallId'] ?? null]);

        if ($isNewCall) {
            if ($event === 'CALL_ENDED') {
                dispatch(new ProcessCallEnded($data));
            } else {
                Log::channel('twin')->info('voice webhook event ignored by new flow', ['event' => $event, 'botId' => $data['botId'] ?? null]);
            }

            return;
        }

        // hybrid: call from a legacy autoCall → legacy job, which only understands CANDIDATE_CHANGED.
        if ($event === 'CANDIDATE_CHANGED') {
            Log::channel('twin')->info('hybrid: voice webhook routed to legacy', ['taskId' => $data['taskId'] ?? null]);
            dispatch(new OperateTwinVoiceWebhook($data));
        } else {
            Log::channel('twin')->info('hybrid: voice webhook event ignored for legacy call', ['event' => $event, 'taskId' => $data['taskId'] ?? null]);
        }
    }

    private function legacy(): WebhookController
    {
        return app(WebhookController::class);
    }

    private function newFlow(): FlowWebhookController
    {
        return app(FlowWebhookController::class);
    }
}
