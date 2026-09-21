<?php

namespace App\Http\Controllers;

use App\Enums\EstaffEvent;
use App\Http\Requests\EstaffWebhook;
use App\Http\Requests\Flow\CallEndedWebhook;
use App\Jobs\Flow\ProcessCallEnded;
use App\Jobs\Flow\ProcessNewCandidate;
use App\Jobs\Flow\StartFlowCall;
use App\Models\InterviewSchedule;
use Illuminate\Support\Facades\Log;

/**
 * Webhook entry points of the new flow (config('flow.mode') === 'new').
 * Reached through FlowSwitchController.
 */
class FlowWebhookController extends Controller
{
    /**
     * ТЗ 3: only `new`, event_type_47 and event_type_48 are processed.
     */
    public function estaffWebhooks(EstaffWebhook $request)
    {
        $data = $request->all();
        Log::channel('estaff')->info('Webhook received (flow)', $data);

        $stateId = $data['data']['state_id'] ?? null;
        $candidateId = (int) ($data['data']['candidate_id'] ?? 0);
        $vacancyId = ! empty($data['data']['vacancy_id']) ? (int) $data['data']['vacancy_id'] : null;

        if (($data['event_type'] ?? null) !== 'candidate_state' || empty($stateId) || $candidateId <= 0) {
            return response()->json('ok', 200);
        }

        switch ($stateId) {
            case EstaffEvent::New->value:
                dispatch(new ProcessNewCandidate($candidateId));
                break;
            case EstaffEvent::Call->value:
                dispatch(new StartFlowCall($candidateId, 'warm', $vacancyId));
                break;
            case EstaffEvent::ColdConversation->value:
                dispatch(new StartFlowCall($candidateId, 'cold', $vacancyId));
                break;
            default:
                Log::channel('estaff')->info('Webhook state ignored (flow)', ['candidate_id' => $candidateId, 'state_id' => $stateId]);
        }

        $this->cancelSchedulesOnStateChange($candidateId, (string) $stateId);

        return response()->json('ok', 200);
    }

    /**
     * ТЗ 5.1: only CALL_ENDED is processed; other events are acknowledged and ignored.
     */
    public function twinVoiceWebhooks(CallEndedWebhook $request)
    {
        $data = $request->all();
        Log::channel('twin')->info('Webhook voice received (flow)', $data);

        if (($data['event'] ?? null) !== 'CALL_ENDED') {
            Log::channel('twin')->info('Webhook voice event ignored (flow)', ['event' => $data['event'] ?? null]);

            return response()->json('ok', 200);
        }

        dispatch(new ProcessCallEnded($data));

        return response()->json('ok', 200);
    }

    /**
     * ТЗ 6.4: a candidate whose state left event_type_49:scheduled must not get reminder / feedback calls.
     */
    private function cancelSchedulesOnStateChange(int $candidateId, string $stateId): void
    {
        $leadState = (string) config('flow.lead_state');
        // Estaff may report the state with or without the ":scheduled" suffix.
        if ($stateId === $leadState || str_starts_with($stateId, explode(':', $leadState)[0])) {
            return;
        }

        $cancelled = InterviewSchedule::where('candidate_id', $candidateId)
            ->active()
            ->update(['stage' => InterviewSchedule::STAGE_CANCELLED, 'skip_reason' => 'state changed to '.$stateId]);

        if ($cancelled > 0) {
            Log::channel('app')->info('flow: interview schedules cancelled after state change', [
                'candidate_id' => $candidateId, 'state_id' => $stateId, 'count' => $cancelled,
            ]);
        }
    }
}
