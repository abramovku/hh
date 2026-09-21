<?php

namespace Tests\Unit\Flow;

use App\Jobs\Flow\ProcessCallEnded;
use App\Models\CallTask;
use App\Models\InterviewSchedule;
use App\Services\Flow\CallResultProcessor;
use Illuminate\Support\Facades\Queue;
use Tests\Flow\FlowTestCase;

class CallResultProcessorTest extends FlowTestCase
{
    private function analyseSession(array $results, string $messages = 'BOT: Алло'): array
    {
        return ['count' => 1, 'items' => [[
            'currentStatusName' => 'ANSWERED',
            'messagesAsString' => $messages,
            'results' => $results,
        ]]];
    }

    public function test_not_answered_sets_event_type_35_without_analyse(): void
    {
        $this->mockTwin()->shouldNotReceive('findSessions');
        $this->mockEstaff()->shouldReceive('setStateCandidate')->once()
            ->with(['candidate' => ['id' => 555, 'state_id' => 'event_type_35']]);

        $result = app(CallResultProcessor::class)->process($this->callEnded(['status' => 'NOANSWER']));

        $this->assertSame(CallResultProcessor::DONE, $result);
    }

    public function test_answered_old_script_bot_sets_event_type_35(): void
    {
        $this->mockTwin()->shouldNotReceive('findSessions');
        $this->mockEstaff()->shouldReceive('setStateCandidate')->once()
            ->with(['candidate' => ['id' => 555, 'state_id' => 'event_type_35']]);

        app(CallResultProcessor::class)->process($this->callEnded(['botId' => config('flow.tasks.old_script.bot')]));
    }

    public function test_callback_data_as_json_string_is_supported(): void
    {
        $this->mockTwin();
        $this->mockEstaff()->shouldReceive('setStateCandidate')->once()
            ->with(['candidate' => ['id' => 777, 'state_id' => 'event_type_35']]);

        app(CallResultProcessor::class)->process($this->callEnded(['status' => 'BUSY', 'callbackData' => '{"EStaffID":"777"}']));
    }

    public function test_no_session_yet_requests_retry(): void
    {
        $this->mockTwin()->shouldReceive('findSessions')->with('79001112233', '2026-07-10T10:49:36+00:00')->once()
            ->andReturn(['count' => 0, 'items' => []]);
        $this->mockEstaff()->shouldNotReceive('setStateCandidate');

        $this->assertSame(CallResultProcessor::RETRY, app(CallResultProcessor::class)->process($this->callEnded()));
    }

    public function test_job_redispatches_itself_with_delay_until_max_attempts(): void
    {
        Queue::fake();
        config(['flow.analyse.max_attempts' => 2, 'flow.analyse.retry_delay' => 30]);
        $this->mockTwin()->shouldReceive('findSessions')->andReturn(['items' => []]);
        $this->mockEstaff();

        (new ProcessCallEnded($this->callEnded(), 1))->handle();
        Queue::assertPushed(ProcessCallEnded::class, 1);

        (new ProcessCallEnded($this->callEnded(), 2))->handle();
        Queue::assertPushed(ProcessCallEnded::class, 1); // no more retries after the last attempt
    }

    public function test_mapped_confirmation_sets_state_with_transcript_comment(): void
    {
        $this->mockTwin()->shouldReceive('findSessions')->once()->andReturn($this->analyseSession([
            'confirmation' => 'ПК_Отказ кандидата-Неактуально',
            'Кандидат_id_EStaff' => '7662714556593886580',
            'Вакансия_id_EStaff' => '7509471325786749795',
        ], 'CLIENT: не актуально'));
        $this->mockEstaff()->shouldReceive('setStateCandidate')->once()->with([
            'candidate' => ['id' => 7662714556593886580, 'state_id' => 'event_type_46'],
            'event' => ['comment' => 'CLIENT: не актуально'],
        ]);

        $this->assertSame(CallResultProcessor::DONE, app(CallResultProcessor::class)->process($this->callEnded()));
    }

    public function test_unmapped_confirmation_is_skipped(): void
    {
        $this->mockTwin()->shouldReceive('findSessions')->once()->andReturn($this->analyseSession(['confirmation' => 'ПК_Что-то новое']));
        $this->mockEstaff()->shouldNotReceive('setStateCandidate');

        $this->assertSame(CallResultProcessor::DONE, app(CallResultProcessor::class)->process($this->callEnded()));
    }

    public function test_lead_sends_add_event_with_user_login_and_schedules_interview(): void
    {
        $this->mockTwin()->shouldReceive('findSessions')->once()->andReturn($this->analyseSession([
            'confirmation' => 'ПК_Лид',
            'Дата_EStaff' => '2026-07-17T00:00',
            'Вакансия_id_EStaff' => '7509471325786749795',
            'Кандидат_id_EStaff' => '555',
            'Вакансия_EStaff_Заказчик' => 'м-н Сургут ТЦ Аура',
        ], 'BOT: Алло'));
        $estaff = $this->mockEstaff();
        $estaff->shouldReceive('eventCandidate')->once()->with([
            'candidate' => ['id' => 555, 'state_id' => 'event_type_49:scheduled'],
            'vacancy' => ['id' => 7509471325786749795],
            'event' => ['date' => '2026-07-17T00:00', 'comment' => 'BOT: Алло', 'user_login' => 'м-н Сургут ТЦ Аура'],
        ])->andReturn(['success' => true]);
        $estaff->shouldReceive('getCandidate')->with(555)->once()->andReturn(['candidate' => ['mobile_phone' => '+7 900 111-22-33']]);

        app(CallResultProcessor::class)->process($this->callEnded());

        $this->assertDatabaseHas('interview_schedules', [
            'candidate_id' => 555,
            'vacancy_id' => 7509471325786749795,
            'phone' => '79001112233',
            'interview_date' => '2026-07-17',
            'stage' => InterviewSchedule::STAGE_SCHEDULED,
            'customer_login' => 'м-н Сургут ТЦ Аура',
        ]);
    }

    public function test_lead_retries_add_event_without_user_login_on_error(): void
    {
        $this->mockTwin()->shouldReceive('findSessions')->once()->andReturn($this->analyseSession([
            'confirmation' => 'ПК_Лид',
            'Дата_EStaff' => '2026-07-17T00:00',
            'Вакансия_id_EStaff' => '1',
            'Кандидат_id_EStaff' => '555',
            'Вакансия_EStaff_Заказчик' => 'bad login',
        ]));
        $estaff = $this->mockEstaff();
        $estaff->shouldReceive('eventCandidate')->once()
            ->with(\Mockery::on(fn ($p) => isset($p['event']['user_login'])))
            ->andThrow(new \Exception('user not found'));
        $estaff->shouldReceive('eventCandidate')->once()
            ->with(\Mockery::on(fn ($p) => ! isset($p['event']['user_login'])))
            ->andReturn([]);
        $estaff->shouldReceive('getCandidate')->andReturn(['candidate' => ['mobile_phone' => '79001112233']]);

        app(CallResultProcessor::class)->process($this->callEnded());

        $this->assertDatabaseCount('interview_schedules', 1);
    }

    public function test_lead_second_failure_is_logged_and_nothing_scheduled(): void
    {
        $this->mockTwin()->shouldReceive('findSessions')->once()->andReturn($this->analyseSession([
            'confirmation' => 'ПК_Лид', 'Дата_EStaff' => '2026-07-17T00:00', 'Вакансия_id_EStaff' => '1',
            'Кандидат_id_EStaff' => '555', 'Вакансия_EStaff_Заказчик' => 'x',
        ]));
        $this->mockEstaff()->shouldReceive('eventCandidate')->twice()->andThrow(new \Exception('fail'));

        app(CallResultProcessor::class)->process($this->callEnded());

        $this->assertDatabaseCount('interview_schedules', 0);
    }

    public function test_lead_is_idempotent_for_same_candidate_and_date(): void
    {
        InterviewSchedule::create([
            'candidate_id' => 555, 'phone' => '79001112233', 'interview_date' => '2026-07-17', 'stage' => 'scheduled',
        ]);
        $this->mockTwin()->shouldReceive('findSessions')->once()->andReturn($this->analyseSession([
            'confirmation' => 'ПК_Лид', 'Дата_EStaff' => '2026-07-17T00:00', 'Вакансия_id_EStaff' => '1', 'Кандидат_id_EStaff' => '555',
        ]));
        $this->mockEstaff()->shouldNotReceive('eventCandidate');

        app(CallResultProcessor::class)->process($this->callEnded());

        $this->assertDatabaseCount('interview_schedules', 1);
    }

    public function test_new_lead_date_supersedes_previous_active_schedule(): void
    {
        InterviewSchedule::create([
            'candidate_id' => 555, 'phone' => '79001112233', 'interview_date' => '2026-07-15', 'stage' => 'scheduled',
        ]);
        $this->mockTwin()->shouldReceive('findSessions')->once()->andReturn($this->analyseSession([
            'confirmation' => 'ПК_Лид', 'Дата_EStaff' => '2026-07-17T00:00', 'Вакансия_id_EStaff' => '1', 'Кандидат_id_EStaff' => '555',
        ]));
        $estaff = $this->mockEstaff();
        $estaff->shouldReceive('eventCandidate')->once()->andReturn([]);
        $estaff->shouldReceive('getCandidate')->andReturn(['candidate' => ['mobile_phone' => '79001112233']]);

        app(CallResultProcessor::class)->process($this->callEnded());

        $this->assertDatabaseHas('interview_schedules', ['interview_date' => '2026-07-15', 'stage' => InterviewSchedule::STAGE_SUPERSEDED]);
        $this->assertDatabaseHas('interview_schedules', ['interview_date' => '2026-07-17', 'stage' => InterviewSchedule::STAGE_SCHEDULED]);
    }

    public function test_reminder_bot_confirmation_schedules_feedback_for_next_day(): void
    {
        InterviewSchedule::create([
            'candidate_id' => 555, 'phone' => '79001112233', 'interview_date' => '2026-07-17',
            'stage' => InterviewSchedule::STAGE_REMINDER_SENT,
        ]);
        $this->mockTwin()->shouldNotReceive('findSessions');
        $this->mockEstaff()->shouldNotReceive('setStateCandidate');

        app(CallResultProcessor::class)->process($this->callEnded([
            'botId' => config('flow.tasks.reminder.bot'),
            'result' => ['confirmation' => 'ПК_Напоминание-Время'],
        ]));

        $this->assertDatabaseHas('interview_schedules', [
            'candidate_id' => 555, 'stage' => InterviewSchedule::STAGE_FEEDBACK_PENDING, 'feedback_date' => '2026-07-18',
        ]);
    }

    public function test_ignored_confirmation_does_not_update_estaff(): void
    {
        $this->mockTwin()->shouldReceive('findSessions')->once()->andReturn($this->analyseSession(['confirmation' => 'ПК_Автоответчик']));
        $this->mockEstaff()->shouldNotReceive('setStateCandidate');

        $this->assertSame(CallResultProcessor::DONE, app(CallResultProcessor::class)->process($this->callEnded()));
    }

    public function test_full_status_table_maps_every_confirmation(): void
    {
        $expected = [
            'ПК_Отказ кандидата' => 'event_type_46',
            'ПК_Отказ кандидата-НеактуальноТЦ' => 'event_type_81',
            'ПК_Наш отказ-Закрыт ТЦ' => 'event_type_45',
            'ПК_Резерв' => 'event_type_43',
            'ПК_Лимит отработок' => 'event_type_83',
            'ПК_Отказ кандидата-Оператор' => 'event_type_82',
        ];
        foreach ($expected as $confirmation => $state) {
            $this->assertSame($state, config('flow.confirmation_states')[$confirmation], $confirmation);
        }
        $this->assertCount(22, config('flow.confirmation_states'));
    }

    public function test_reminder_bot_not_relevant_result_sets_event_type_46_with_transcript(): void
    {
        InterviewSchedule::create([
            'candidate_id' => 555, 'phone' => '79001112233', 'interview_date' => '2026-07-17',
            'stage' => InterviewSchedule::STAGE_REMINDER_SENT,
        ]);
        $this->mockTwin()->shouldReceive('findSessions')->once()
            ->andReturn($this->analyseSession(['confirmation' => 'ПК_Напоминание-Неактуально'], 'CLIENT: передумал'));
        $this->mockEstaff()->shouldReceive('setStateCandidate')->once()->with([
            'candidate' => ['id' => 555, 'state_id' => 'event_type_46'],
            'event' => ['comment' => 'CLIENT: передумал'],
        ]);

        app(CallResultProcessor::class)->process($this->callEnded([
            'botId' => config('flow.tasks.reminder.bot'),
            'result' => ['confirmation' => 'ПК_Напоминание-Неактуально'],
        ]));

        // Row stays until Estaff echoes the state change through the candidate_state webhook.
        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 555, 'stage' => InterviewSchedule::STAGE_REMINDER_SENT]);
    }

    public function test_reminder_bot_without_webhook_result_reads_confirmation_from_session(): void
    {
        InterviewSchedule::create([
            'candidate_id' => 555, 'phone' => '79001112233', 'interview_date' => '2026-07-17',
            'stage' => InterviewSchedule::STAGE_REMINDER_SENT,
        ]);
        $this->mockTwin()->shouldReceive('findSessions')->once()->andReturn($this->analyseSession(['confirmation' => 'ПК_Напоминание-Время']));
        $this->mockEstaff()->shouldNotReceive('setStateCandidate');

        app(CallResultProcessor::class)->process($this->callEnded(['botId' => config('flow.tasks.reminder.bot'), 'result' => []]));

        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 555, 'stage' => InterviewSchedule::STAGE_FEEDBACK_PENDING]);
    }

    public function test_reminder_bot_not_answered_does_not_touch_estaff_state(): void
    {
        $this->mockTwin()->shouldNotReceive('findSessions');
        $this->mockEstaff()->shouldNotReceive('setStateCandidate');

        $result = app(CallResultProcessor::class)->process($this->callEnded([
            'botId' => config('flow.tasks.reminder.bot'), 'status' => 'NOANSWER',
        ]));

        $this->assertSame(CallResultProcessor::DONE, $result);
    }

    public function test_feedback_bot_webhook_is_ignored(): void
    {
        $this->mockTwin()->shouldNotReceive('findSessions');
        $this->mockEstaff()->shouldNotReceive('setStateCandidate');

        app(CallResultProcessor::class)->process($this->callEnded([
            'botId' => config('flow.tasks.feedback.bot'), 'result' => ['confirmation' => 'ПК_ОС-Пришел'],
        ]));
    }

    public function test_task_type_falls_back_to_call_tasks_when_bot_unknown(): void
    {
        CallTask::create(['date' => '2026-07-18', 'type' => 'feedback', 'twin_id' => 'task-1']);
        $this->mockTwin()->shouldNotReceive('findSessions');
        $this->mockEstaff()->shouldNotReceive('setStateCandidate');

        app(CallResultProcessor::class)->process($this->callEnded(['botId' => 'unknown-bot', 'taskId' => 'task-1']));
    }
}
