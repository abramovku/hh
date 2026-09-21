<?php

namespace App\Services\Flow;

use App\Models\InterviewSchedule;
use Illuminate\Support\Facades\Log;

/**
 * CALL_ENDED from the «ПК_Напоминание» task (ТЗ 6.5): when the candidate confirmed the
 * interview time, queue the feedback call for the next day (created by app:flow-feedback).
 */
class ReminderResultHandler
{
    public function handle(?int $candidateId, ?string $confirmation, array $webhook): void
    {
        if ($confirmation !== config('flow.reminder_confirmation')) {
            Log::channel('twin')->info('flow reminder: confirmation does not require feedback call', [
                'candidate_id' => $candidateId, 'confirmation' => $confirmation, 'status' => $webhook['status'] ?? null,
            ]);

            return;
        }

        if ($candidateId === null) {
            Log::channel('app')->error('flow reminder: no callbackData.EStaffID in webhook', ['webhook' => $webhook]);

            return;
        }

        $schedule = InterviewSchedule::where('candidate_id', $candidateId)
            ->where('stage', InterviewSchedule::STAGE_REMINDER_SENT)
            ->orderByDesc('interview_date')
            ->first();

        if ($schedule === null) {
            Log::channel('app')->warning('flow reminder: no interview schedule in stage reminder_sent for candidate', [
                'candidate_id' => $candidateId,
            ]);

            return;
        }

        $schedule->stage = InterviewSchedule::STAGE_FEEDBACK_PENDING;
        $schedule->feedback_date = $schedule->interview_date->copy()->addDay()->toDateString();
        $schedule->save();

        Log::channel('app')->info('flow reminder: feedback call scheduled', [
            'candidate_id' => $candidateId, 'feedback_date' => $schedule->feedback_date->toDateString(),
        ]);
    }
}
