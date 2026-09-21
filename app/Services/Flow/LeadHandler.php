<?php

namespace App\Services\Flow;

use App\Models\InterviewSchedule;
use App\Support\Flow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ПК_Лид: send event_type_49:scheduled to Estaff (ТЗ 5.6) and remember the interview
 * date for the reminder / feedback calls (ТЗ 6).
 */
class LeadHandler
{
    private const DATE_FORMATS = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d', 'd.m.Y H:i', 'd.m.Y'];

    public function handle(int $candidateId, array $results, string $comment): void
    {
        $vacancyId = isset($results['Вакансия_id_EStaff']) ? (int) $results['Вакансия_id_EStaff'] : null;
        $rawDate = isset($results['Дата_EStaff']) ? (string) $results['Дата_EStaff'] : '';
        $customer = isset($results['Вакансия_EStaff_Заказчик']) ? trim((string) $results['Вакансия_EStaff_Заказчик']) : '';
        $interviewAt = $this->parseDate($rawDate);

        if (empty($vacancyId)) {
            // candidate/add_event requires the vacancy block; every Twin result must carry Вакансия_id_EStaff (ТЗ 5.3).
            Log::channel('app')->error('flow lead: results have no Вакансия_id_EStaff, add_event not sent', [
                'candidate_id' => $candidateId, 'results' => $results,
            ]);

            return;
        }

        if ($interviewAt !== null && $this->alreadyScheduled($candidateId, $interviewAt)) {
            Log::channel('twin')->info('flow lead: interview already scheduled for this date, add_event skipped', [
                'candidate_id' => $candidateId, 'interview_date' => $interviewAt->toDateString(),
            ]);

            return;
        }

        $params = [
            'candidate' => ['id' => $candidateId, 'state_id' => config('flow.lead_state')],
            'vacancy' => ['id' => $vacancyId],
            'event' => ['date' => $rawDate, 'comment' => $comment],
        ];
        if ($customer !== '') {
            $params['event']['user_login'] = $customer;
        }

        if (! $this->sendEvent($candidateId, $params)) {
            return;
        }

        if ($interviewAt === null) {
            Log::channel('app')->error('flow lead: Дата_EStaff is not parseable, reminder will not be scheduled', [
                'candidate_id' => $candidateId, 'date' => $rawDate,
            ]);

            return;
        }

        $this->schedule($candidateId, $vacancyId, $interviewAt, $customer, $results, $comment);
    }

    /**
     * eventCandidate with user_login, retried without it on error (ТЗ 5.6 «Обработка ошибки»).
     */
    private function sendEvent(int $candidateId, array $params): bool
    {
        $estaff = app('estaff');

        try {
            $estaff->eventCandidate($params);
            Log::channel('twin')->info('flow lead: event_type_49:scheduled sent', ['candidate_id' => $candidateId]);

            return true;
        } catch (\Throwable $first) {
            if (! isset($params['event']['user_login'])) {
                $this->logFailure($candidateId, $first, 'without user_login');

                return false;
            }

            Log::channel('estaff')->warning('flow lead: add_event with user_login failed, retrying without it', [
                'candidate_id' => $candidateId, 'user_login' => $params['event']['user_login'], 'message' => $first->getMessage(),
            ]);
            unset($params['event']['user_login']);
        }

        try {
            $estaff->eventCandidate($params);
            Log::channel('twin')->info('flow lead: event_type_49:scheduled sent without user_login', ['candidate_id' => $candidateId]);

            return true;
        } catch (\Throwable $second) {
            $this->logFailure($candidateId, $second, 'retry without user_login');

            return false;
        }
    }

    private function schedule(int $candidateId, ?int $vacancyId, CarbonImmutable $interviewAt, string $customer, array $results, string $comment): void
    {
        $phone = app('estaff')->getCandidate($candidateId)['candidate']['mobile_phone'] ?? '';
        $phone = str_replace(['+', '(', ')', '-', ' '], '', explode(',', (string) $phone)[0]);

        DB::transaction(function () use ($candidateId, $vacancyId, $interviewAt, $customer, $results, $comment, $phone) {
            InterviewSchedule::where('candidate_id', $candidateId)
                ->active()
                ->update(['stage' => InterviewSchedule::STAGE_SUPERSEDED]);

            InterviewSchedule::create([
                'candidate_id' => $candidateId,
                'vacancy_id' => $vacancyId,
                'phone' => $phone,
                'interview_date' => $interviewAt->toDateString(),
                'interview_at' => $interviewAt->toDateTimeString(),
                'customer_login' => $customer !== '' ? $customer : null,
                'stage' => InterviewSchedule::STAGE_SCHEDULED,
                'results' => $results,
                'comment' => $comment,
            ]);
        });

        Log::channel('app')->info('flow lead: interview scheduled', [
            'candidate_id' => $candidateId, 'interview_date' => $interviewAt->toDateString(),
        ]);
    }

    private function alreadyScheduled(int $candidateId, CarbonImmutable $interviewAt): bool
    {
        return InterviewSchedule::where('candidate_id', $candidateId)
            ->whereDate('interview_date', $interviewAt->toDateString())
            ->exists();
    }

    public function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value, Flow::timezone());
            } catch (\Throwable) {
                continue;
            }
            if ($date !== false && $date->format($format) === $value) {
                return $date;
            }
        }

        try {
            return CarbonImmutable::parse($value, Flow::timezone());
        } catch (\Throwable) {
            return null;
        }
    }

    private function logFailure(int $candidateId, \Throwable $e, string $stage): void
    {
        Log::channel('app')->error('flow lead: add_event failed ('.$stage.')', [
            'candidate_id' => $candidateId, 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
        ]);
    }
}
