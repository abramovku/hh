<?php

namespace App\Console\Commands\Flow;

use App\Models\InterviewSchedule;
use App\Services\Flow\LeadHandler;
use App\Support\Flow;
use App\Traits\SanitizesPhone;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * ТЗ 6.1–6.4: on the interview day add every lead that is still in event_type_49:scheduled
 * for that date to the «ПК_Напоминание {date}» autoCall. Idempotent: safe to run every 15 minutes.
 */
class SendInterviewReminders extends Command
{
    use SanitizesPhone;

    protected $signature = 'app:flow-reminders {--date= : Interview date (Y-m-d) in the business timezone, default today}';

    protected $description = 'Add today\'s scheduled interviews to the Twin reminder autoCall';

    public function handle(): int
    {
        $date = $this->option('date') ?: Flow::today();
        $day = CarbonImmutable::parse($date, Flow::timezone());
        $rows = InterviewSchedule::dueForReminder($day->toDateString())->orderBy('id')->get();

        $this->info("flow-reminders {$day->toDateString()}: {$rows->count()} candidate(s) to check");
        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

        $twin = app('twin');
        $estaff = app('estaff');
        $autoCallId = null;
        $sent = 0;

        foreach ($rows as $row) {
            try {
                $skip = $this->skipReason($estaff, $row);
                if ($skip !== null) {
                    $row->update(['stage' => InterviewSchedule::STAGE_REMINDER_SKIPPED, 'skip_reason' => $skip]);
                    Log::channel('app')->info('flow reminder: candidate skipped', ['candidate_id' => $row->candidate_id, 'reason' => $skip]);

                    continue;
                }

                // Claim the row before calling Twin so a parallel run cannot add the candidate twice.
                $claimed = InterviewSchedule::where('id', $row->id)
                    ->where('stage', InterviewSchedule::STAGE_SCHEDULED)
                    ->update(['stage' => InterviewSchedule::STAGE_REMINDER_SENT]);
                if ($claimed === 0) {
                    continue;
                }

                if ($autoCallId === null) {
                    $created = false;
                    $autoCallId = $twin->getAutoCall('reminder', $day, $created);
                    if ($created && (int) config('flow.sleep_after_create', 0) > 0) {
                        sleep((int) config('flow.sleep_after_create'));
                    }
                }

                $twin->addCandidateToAutoCall(
                    $autoCallId,
                    $row->phone,
                    (string) $row->candidate_id,
                    $this->normalizePhone11($row->phone) ?? $row->phone
                );

                $row->update(['reminder_autocall_id' => $autoCallId, 'reminder_sent_at' => now()]);
                $sent++;
                Log::channel('app')->info('flow reminder: candidate added', ['candidate_id' => $row->candidate_id, 'autocall_id' => $autoCallId]);
            } catch (\Throwable $e) {
                InterviewSchedule::where('id', $row->id)
                    ->where('stage', InterviewSchedule::STAGE_REMINDER_SENT)
                    ->whereNull('reminder_sent_at')
                    ->update(['stage' => InterviewSchedule::STAGE_SCHEDULED]);

                Log::channel('app')->error('flow reminder: failed for candidate', [
                    'candidate_id' => $row->candidate_id, 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                ]);
                $this->error("candidate {$row->candidate_id}: {$e->getMessage()}");
            }
        }

        $this->info("flow-reminders: {$sent} candidate(s) added");

        return self::SUCCESS;
    }

    /**
     * ТЗ 6.4: still event_type_49:scheduled and the state date matches the interview date.
     * When Estaff does not return the state fields, only local bookkeeping is trusted.
     */
    private function skipReason(object $estaff, InterviewSchedule $row): ?string
    {
        $state = $estaff->getCandidateState($row->candidate_id);
        $leadState = (string) config('flow.lead_state');

        if ($state['state'] === null) {
            Log::channel('estaff')->warning('flow reminder: Estaff returned no state field, relying on local state only', [
                'candidate_id' => $row->candidate_id, 'fields' => config('flow.estaff_state_fields'),
            ]);

            return null;
        }

        if ($state['state'] !== $leadState && ! str_starts_with($state['state'], explode(':', $leadState)[0])) {
            return 'state is '.$state['state'];
        }

        if (! empty($state['state_date'])) {
            $stateDate = app(LeadHandler::class)->parseDate($state['state_date']);
            if ($stateDate !== null && $stateDate->toDateString() !== $row->interview_date->toDateString()) {
                return 'rescheduled to '.$stateDate->toDateString();
            }
        }

        return null;
    }
}
