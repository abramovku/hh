<?php

namespace App\Console\Commands\Flow;

use App\Models\InterviewSchedule;
use App\Support\Flow;
use App\Traits\SanitizesPhone;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * ТЗ 6.6: the day after the interview, add candidates who confirmed the reminder
 * («ПК_Напоминание-Время») to the «ПК_ОС {date}» autoCall. Idempotent.
 */
class SendFeedbackCalls extends Command
{
    use SanitizesPhone;

    protected $signature = 'app:flow-feedback {--date= : Feedback date (Y-m-d) in the business timezone, default today}';

    protected $description = 'Add candidates to the Twin feedback autoCall the day after their interview';

    public function handle(): int
    {
        $date = $this->option('date') ?: Flow::today();
        $day = CarbonImmutable::parse($date, Flow::timezone());
        $rows = InterviewSchedule::dueForFeedback($day->toDateString())->orderBy('id')->get();

        $this->info("flow-feedback {$day->toDateString()}: {$rows->count()} candidate(s)");
        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

        $twin = app('twin');
        $autoCallId = null;
        $sent = 0;

        foreach ($rows as $row) {
            try {
                $claimed = InterviewSchedule::where('id', $row->id)
                    ->where('stage', InterviewSchedule::STAGE_FEEDBACK_PENDING)
                    ->update(['stage' => InterviewSchedule::STAGE_FEEDBACK_SENT]);
                if ($claimed === 0) {
                    continue;
                }

                if ($autoCallId === null) {
                    $created = false;
                    $autoCallId = $twin->getAutoCall('feedback', $day, $created);
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

                $row->update(['feedback_autocall_id' => $autoCallId, 'feedback_sent_at' => now()]);
                $sent++;
                Log::channel('app')->info('flow feedback: candidate added', ['candidate_id' => $row->candidate_id, 'autocall_id' => $autoCallId]);
            } catch (\Throwable $e) {
                InterviewSchedule::where('id', $row->id)
                    ->where('stage', InterviewSchedule::STAGE_FEEDBACK_SENT)
                    ->whereNull('feedback_sent_at')
                    ->update(['stage' => InterviewSchedule::STAGE_FEEDBACK_PENDING]);

                Log::channel('app')->error('flow feedback: failed for candidate', [
                    'candidate_id' => $row->candidate_id, 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                ]);
                $this->error("candidate {$row->candidate_id}: {$e->getMessage()}");
            }
        }

        $this->info("flow-feedback: {$sent} candidate(s) added");

        return self::SUCCESS;
    }
}
