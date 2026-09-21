<?php

namespace Tests\Unit\Flow;

use App\Models\InterviewSchedule;
use Carbon\CarbonImmutable;
use Tests\Flow\FlowTestCase;

class FlowCommandsTest extends FlowTestCase
{
    private function schedule(int $candidateId, string $date, string $stage = InterviewSchedule::STAGE_SCHEDULED, array $extra = []): InterviewSchedule
    {
        return InterviewSchedule::create(array_replace([
            'candidate_id' => $candidateId, 'phone' => '7900111'.str_pad((string) $candidateId, 4, '0', STR_PAD_LEFT),
            'interview_date' => $date, 'stage' => $stage,
        ], $extra));
    }

    public function test_reminders_are_added_only_for_candidates_still_scheduled_on_that_date(): void
    {
        $this->schedule(1, '2026-07-17');                                  // still scheduled → added
        $this->schedule(2, '2026-07-17');                                  // state changed → skipped
        $this->schedule(3, '2026-07-17');                                  // rescheduled → skipped
        $this->schedule(4, '2026-07-18');                                  // other date → untouched
        $this->schedule(5, '2026-07-17', InterviewSchedule::STAGE_CANCELLED); // cancelled → untouched

        $estaff = $this->mockEstaff();
        $estaff->shouldReceive('getCandidateState')->with(1)->once()->andReturn(['state' => 'event_type_49:scheduled', 'state_date' => '2026-07-17T00:00']);
        $estaff->shouldReceive('getCandidateState')->with(2)->once()->andReturn(['state' => 'event_type_35', 'state_date' => '2026-07-17T00:00']);
        $estaff->shouldReceive('getCandidateState')->with(3)->once()->andReturn(['state' => 'event_type_49:scheduled', 'state_date' => '2026-07-20T00:00']);

        $twin = $this->mockTwin();
        $twin->shouldReceive('getAutoCall')->once()
            ->with('reminder', \Mockery::type(CarbonImmutable::class), \Mockery::any())
            ->andReturn('ac-rem-1');
        $twin->shouldReceive('addCandidateToAutoCall')->once()->with('ac-rem-1', '79001110001', '1', '79001110001')->andReturn([]);

        $this->artisan('app:flow-reminders', ['--date' => '2026-07-17'])->assertSuccessful();

        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 1, 'stage' => InterviewSchedule::STAGE_REMINDER_SENT, 'reminder_autocall_id' => 'ac-rem-1']);
        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 2, 'stage' => InterviewSchedule::STAGE_REMINDER_SKIPPED, 'skip_reason' => 'state is event_type_35']);
        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 3, 'stage' => InterviewSchedule::STAGE_REMINDER_SKIPPED, 'skip_reason' => 'rescheduled to 2026-07-20']);
        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 4, 'stage' => InterviewSchedule::STAGE_SCHEDULED]);
        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 5, 'stage' => InterviewSchedule::STAGE_CANCELLED]);
    }

    public function test_reminders_command_is_idempotent_and_reverts_row_on_twin_failure(): void
    {
        $this->schedule(1, '2026-07-17');
        $this->mockEstaff()->shouldReceive('getCandidateState')->andReturn(['state' => 'event_type_49:scheduled', 'state_date' => null]);

        $twin = $this->mockTwin();
        $twin->shouldReceive('getAutoCall')->once()->andReturn('ac-rem-1');
        $twin->shouldReceive('addCandidateToAutoCall')->once()->andThrow(new \Exception('twin down'));

        $this->artisan('app:flow-reminders', ['--date' => '2026-07-17'])->assertSuccessful();
        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 1, 'stage' => InterviewSchedule::STAGE_SCHEDULED]);

        // Second run succeeds and does not add twice.
        $twin->shouldReceive('getAutoCall')->once()->andReturn('ac-rem-1');
        $twin->shouldReceive('addCandidateToAutoCall')->once()->andReturn([]);
        $this->artisan('app:flow-reminders', ['--date' => '2026-07-17'])->assertSuccessful();
        $this->artisan('app:flow-reminders', ['--date' => '2026-07-17'])->assertSuccessful();

        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 1, 'stage' => InterviewSchedule::STAGE_REMINDER_SENT]);
    }

    public function test_reminders_without_estaff_state_fields_rely_on_local_state(): void
    {
        $this->schedule(1, '2026-07-17');
        $this->mockEstaff()->shouldReceive('getCandidateState')->andReturn(['state' => null, 'state_date' => null]);
        $twin = $this->mockTwin();
        $twin->shouldReceive('getAutoCall')->once()->andReturn('ac-rem-1');
        $twin->shouldReceive('addCandidateToAutoCall')->once()->andReturn([]);

        $this->artisan('app:flow-reminders', ['--date' => '2026-07-17'])->assertSuccessful();

        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 1, 'stage' => InterviewSchedule::STAGE_REMINDER_SENT]);
    }

    public function test_feedback_calls_are_added_for_pending_rows_of_the_day(): void
    {
        $this->schedule(1, '2026-07-17', InterviewSchedule::STAGE_FEEDBACK_PENDING, ['feedback_date' => '2026-07-18']);
        $this->schedule(2, '2026-07-17', InterviewSchedule::STAGE_REMINDER_SENT);
        $this->schedule(3, '2026-07-18', InterviewSchedule::STAGE_FEEDBACK_PENDING, ['feedback_date' => '2026-07-19']);

        $this->mockEstaff()->shouldNotReceive('getCandidateState');
        $twin = $this->mockTwin();
        $twin->shouldReceive('getAutoCall')->once()->with('feedback', \Mockery::type(CarbonImmutable::class), \Mockery::any())->andReturn('ac-fb-1');
        $twin->shouldReceive('addCandidateToAutoCall')->once()->with('ac-fb-1', '79001110001', '1', '79001110001')->andReturn([]);

        $this->artisan('app:flow-feedback', ['--date' => '2026-07-18'])->assertSuccessful();

        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 1, 'stage' => InterviewSchedule::STAGE_FEEDBACK_SENT, 'feedback_autocall_id' => 'ac-fb-1']);
        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 2, 'stage' => InterviewSchedule::STAGE_REMINDER_SENT]);
        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 3, 'stage' => InterviewSchedule::STAGE_FEEDBACK_PENDING]);
    }

    public function test_commands_do_nothing_when_no_rows(): void
    {
        $this->mockTwin()->shouldNotReceive('getAutoCall');
        $this->mockEstaff();

        $this->artisan('app:flow-reminders', ['--date' => '2026-07-17'])->assertSuccessful();
        $this->artisan('app:flow-feedback', ['--date' => '2026-07-17'])->assertSuccessful();
    }
}
