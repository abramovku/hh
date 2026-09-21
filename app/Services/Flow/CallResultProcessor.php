<?php

namespace App\Services\Flow;

use App\Models\CallTask;
use App\Support\Flow;
use App\Traits\SanitizesPhone;
use Illuminate\Support\Facades\Log;

/**
 * Twin CALL_ENDED webhook → Estaff update (ТЗ 5, 6.5).
 *
 * Order of checks:
 *  1. task type by botId (fallback: call_tasks by taskId/autoCallId)
 *  2. feedback task      → nothing
 *  3. reminder task      → ПК_Напоминание-Время → feedback call; other results → «СТАТУСЫ БОТА» table
 *  4. status != ANSWERED → event_type_35
 *  5. old-script bot     → event_type_35
 *  6. analyse session    → confirmation → LeadHandler | confirmation_states | ignored | unmapped
 */
class CallResultProcessor
{
    use SanitizesPhone;

    public const DONE = 'done';

    /** Analyse has no session yet: the job should re-dispatch itself later. */
    public const RETRY = 'retry';

    public function __construct(
        private readonly LeadHandler $leads,
        private readonly ReminderResultHandler $reminders,
    ) {}

    public function process(array $webhook): string
    {
        $candidateId = $this->candidateId($webhook);
        $status = strtoupper((string) ($webhook['status'] ?? ''));
        $taskKey = Flow::taskKeyByBot($webhook['botId'] ?? null) ?? $this->taskKeyByCallTask($webhook);

        Log::channel('twin')->info('flow call ended', [
            'candidate_id' => $candidateId, 'status' => $status, 'task' => $taskKey,
            'bot' => $webhook['botId'] ?? null, 'callTo' => $webhook['callTo'] ?? null,
        ]);

        if ($taskKey === 'feedback') {
            Log::channel('twin')->info('flow call ended: feedback task results are not processed', ['candidate_id' => $candidateId]);

            return self::DONE;
        }

        if ($taskKey === 'reminder') {
            return $this->processReminder($webhook, $candidateId, $status);
        }

        if ($status !== 'ANSWERED') {
            $this->setState($candidateId, config('flow.states.not_answered'), 'call not answered', $webhook);

            return self::DONE;
        }

        if ($taskKey === 'old_script') {
            $this->setState($candidateId, config('flow.states.not_answered'), 'old script bot answered', $webhook);

            return self::DONE;
        }

        $session = $this->session($webhook);
        if ($session === null) {
            return self::RETRY;
        }

        $results = is_array($session['results'] ?? null) ? $session['results'] : [];
        $confirmation = $results['confirmation'] ?? null;

        if (! is_scalar($confirmation) || $confirmation === '') {
            Log::channel('twin')->warning('flow call ended: session has no results.confirmation', [
                'candidate_id' => $candidateId, 'session' => $session,
            ]);

            return self::DONE;
        }

        $resultCandidateId = (int) ($results['Кандидат_id_EStaff'] ?? 0) ?: $candidateId;
        if ($resultCandidateId === null) {
            Log::channel('app')->error('flow call ended: no candidate id in callbackData nor in results', ['webhook' => $webhook]);

            return self::DONE;
        }

        $this->applyConfirmation($resultCandidateId, (string) $confirmation, $results, (string) ($session['messagesAsString'] ?? ''), $webhook);

        return self::DONE;
    }

    /**
     * ТЗ 6.5: result.confirmation comes in the webhook itself. ПК_Напоминание-Время queues the feedback call;
     * every other result goes through the «СТАТУСЫ БОТА» table (e.g. ПК_Напоминание-Неактуально → event_type_46).
     * A missed reminder call leaves the Estaff state untouched.
     */
    private function processReminder(array $webhook, ?int $candidateId, string $status): string
    {
        $confirmation = $webhook['result']['confirmation'] ?? null;
        $confirmation = is_scalar($confirmation) && $confirmation !== '' ? (string) $confirmation : null;
        $comment = '';

        if ($confirmation === null && $status === 'ANSWERED') {
            $session = $this->session($webhook);
            if ($session === null) {
                return self::RETRY;
            }
            $confirmation = $session['results']['confirmation'] ?? null;
            $confirmation = is_scalar($confirmation) && $confirmation !== '' ? (string) $confirmation : null;
            $comment = (string) ($session['messagesAsString'] ?? '');
        }

        if ($confirmation === null) {
            Log::channel('twin')->info('flow reminder: no result, Estaff state untouched', [
                'candidate_id' => $candidateId, 'status' => $status,
            ]);

            return self::DONE;
        }

        if ($confirmation === config('flow.reminder_confirmation')) {
            $this->reminders->handle($candidateId, $confirmation, $webhook);

            return self::DONE;
        }

        if ($candidateId === null) {
            Log::channel('app')->error('flow reminder: no callbackData.EStaffID, result not applied', ['confirmation' => $confirmation, 'webhook' => $webhook]);

            return self::DONE;
        }

        if ($comment === '' && isset(config('flow.confirmation_states')[$confirmation])) {
            $session = $this->session($webhook);
            $comment = (string) ($session['messagesAsString'] ?? '');
        }

        $this->applyConfirmation($candidateId, $confirmation, [], $comment, $webhook);

        return self::DONE;
    }

    /**
     * ТЗ 5.4 / 5.6: ПК_Лид → add_event; table → set_state; «Ничего не делать» → nothing; unknown → log and stop.
     */
    private function applyConfirmation(int $candidateId, string $confirmation, array $results, string $comment, array $webhook): void
    {
        if ($confirmation === config('flow.lead_confirmation')) {
            $this->leads->handle($candidateId, $results, $comment);

            return;
        }

        if (in_array($confirmation, (array) config('flow.ignored_confirmations', []), true)) {
            Log::channel('twin')->info('flow call ended: confirmation requires no Estaff update', [
                'candidate_id' => $candidateId, 'confirmation' => $confirmation,
            ]);

            return;
        }

        $state = config('flow.confirmation_states')[$confirmation] ?? null;
        if (empty($state)) {
            Log::channel('twin')->warning('flow call ended: confirmation is not mapped to an Estaff state, skipped', [
                'candidate_id' => $candidateId, 'confirmation' => $confirmation,
            ]);

            return;
        }

        $this->setState($candidateId, $state, "confirmation $confirmation", $webhook, $comment);
    }

    /**
     * Estaff candidate id from callbackData.EStaffID (array, object or JSON string).
     */
    public function candidateId(array $webhook): ?int
    {
        $callback = $webhook['callbackData'] ?? null;

        if (is_string($callback)) {
            $decoded = json_decode($callback, true);
            $callback = is_array($decoded) ? $decoded : ['EStaffID' => $callback];
        }

        $id = is_array($callback) ? ($callback['EStaffID'] ?? null) : null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private function taskKeyByCallTask(array $webhook): ?string
    {
        $ids = array_values(array_filter([$webhook['autoCallId'] ?? null, $webhook['taskId'] ?? null]));
        if ($ids === []) {
            return null;
        }

        $type = CallTask::whereIn('twin_id', $ids)->value('type');

        return is_string($type) && config("flow.tasks.$type") ? $type : null;
    }

    /**
     * First analyse session for the call, or null when Twin has none yet.
     */
    private function session(array $webhook): ?array
    {
        $callTo = (string) ($webhook['callTo'] ?? '');
        $phone = $this->normalizePhone11($callTo) ?? $callTo;
        $from = (string) ($webhook['startedAt'] ?? '');

        if ($phone === '') {
            Log::channel('twin')->error('flow call ended: webhook has no callTo, cannot query session', ['webhook' => $webhook]);

            return [];
        }

        try {
            $data = app('twin')->findSessions($phone, $from);
        } catch (\Throwable $e) {
            Log::channel('twin')->error('flow call ended: analyse request failed', ['phone' => $phone, 'message' => $e->getMessage()]);

            return null;
        }

        $item = $data['items'][0] ?? null;

        if (! is_array($item)) {
            Log::channel('twin')->info('flow call ended: no analyse session yet', ['phone' => $phone, 'from' => $from]);

            return null;
        }

        return $item;
    }

    private function setState(?int $candidateId, string $stateId, string $reason, array $webhook, ?string $comment = null): void
    {
        if ($candidateId === null) {
            Log::channel('app')->error('flow call ended: no callbackData.EStaffID, state not updated', [
                'state' => $stateId, 'reason' => $reason, 'webhook' => $webhook,
            ]);

            return;
        }

        $params = ['candidate' => ['id' => $candidateId, 'state_id' => $stateId]];
        if ($comment !== null && $comment !== '') {
            $params['event'] = ['comment' => $comment];
        }

        try {
            app('estaff')->setStateCandidate($params);
            Log::channel('twin')->info('flow call ended: candidate state updated', [
                'candidate_id' => $candidateId, 'state' => $stateId, 'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            Log::channel('app')->error('flow call ended: set_state failed', [
                'candidate_id' => $candidateId, 'state' => $stateId, 'reason' => $reason,
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
        }
    }
}
