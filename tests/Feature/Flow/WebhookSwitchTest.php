<?php

namespace Tests\Feature\Flow;

use App\Jobs\Flow\ProcessCallEnded;
use App\Jobs\Flow\ProcessNewCandidate;
use App\Jobs\Flow\StartFlowCall;
use App\Jobs\OperateTwinVoiceWebhook;
use App\Jobs\StartTwinCall;
use App\Jobs\StartTwinColdConversation;
use App\Models\InterviewSchedule;
use Illuminate\Support\Facades\Queue;
use Tests\Flow\FlowTestCase;

class WebhookSwitchTest extends FlowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function estaffWebhook(string $stateId, int $candidateId = 555, ?int $vacancyId = 42): array
    {
        return ['event_type' => 'candidate_state', 'data' => ['state_id' => $stateId, 'candidate_id' => $candidateId, 'vacancy_id' => $vacancyId]];
    }

    public function test_new_mode_dispatches_flow_jobs_for_the_three_states_only(): void
    {
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('new'))->assertOk();
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_47'))->assertOk();
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_48'))->assertOk();
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_32'))->assertOk();
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_44'))->assertOk();

        Queue::assertPushed(ProcessNewCandidate::class, 1);
        Queue::assertPushed(StartFlowCall::class, 2);
        Queue::assertNotPushed(StartTwinCall::class);
        Queue::assertNotPushed(StartTwinColdConversation::class);
    }

    public function test_new_mode_ignores_webhooks_without_candidate_id(): void
    {
        $this->postJson('/api/estaff-webhooks', ['event_type' => 'candidate_state', 'data' => ['state_id' => 'event_type_47']])->assertOk();
        $this->postJson('/api/estaff-webhooks', ['event_type' => 'test'])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_new_mode_cancels_active_schedules_when_candidate_state_changes(): void
    {
        InterviewSchedule::create(['candidate_id' => 555, 'phone' => '79001112233', 'interview_date' => '2026-07-17', 'stage' => InterviewSchedule::STAGE_SCHEDULED]);
        InterviewSchedule::create(['candidate_id' => 555, 'phone' => '79001112233', 'interview_date' => '2026-07-10', 'stage' => InterviewSchedule::STAGE_SUPERSEDED]);

        // Our own event_type_49:scheduled echo must not cancel anything.
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_49:scheduled'))->assertOk();
        $this->assertDatabaseHas('interview_schedules', ['interview_date' => '2026-07-17', 'stage' => InterviewSchedule::STAGE_SCHEDULED]);

        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_46'))->assertOk();
        $this->assertDatabaseHas('interview_schedules', ['interview_date' => '2026-07-17', 'stage' => InterviewSchedule::STAGE_CANCELLED]);
        $this->assertDatabaseHas('interview_schedules', ['interview_date' => '2026-07-10', 'stage' => InterviewSchedule::STAGE_SUPERSEDED]);
    }

    public function test_new_mode_voice_webhook_processes_call_ended_only(): void
    {
        $this->postJson('/api/twin-webhooks-voice', $this->callEnded())->assertOk();
        Queue::assertPushed(ProcessCallEnded::class, 1);

        $this->postJson('/api/twin-webhooks-voice', ['event' => 'CANDIDATE_CHANGED', 'taskId' => 't1', 'lastCallId' => 'c1'])->assertOk();
        Queue::assertPushed(ProcessCallEnded::class, 1);
        Queue::assertNotPushed(OperateTwinVoiceWebhook::class);
    }

    public function test_new_mode_voice_webhook_validates_call_ended_fields(): void
    {
        $this->postJson('/api/twin-webhooks-voice', ['event' => 'CALL_ENDED'])->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_legacy_mode_keeps_old_handlers(): void
    {
        config(['flow.mode' => 'legacy']);

        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_47'))->assertOk();
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_48'))->assertOk();
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('new'))->assertOk();

        Queue::assertPushed(StartTwinCall::class, 1);
        Queue::assertPushed(StartTwinColdConversation::class, 1);
        Queue::assertNotPushed(StartFlowCall::class);
        Queue::assertNotPushed(ProcessNewCandidate::class);

        // Legacy voice request still requires CANDIDATE_CHANGED.
        $this->postJson('/api/twin-webhooks-voice', $this->callEnded())->assertStatus(422);
        $this->postJson('/api/twin-webhooks-voice', ['event' => 'CANDIDATE_CHANGED', 'taskId' => 't1'])->assertOk();
        Queue::assertPushed(OperateTwinVoiceWebhook::class, 1);
        Queue::assertNotPushed(ProcessCallEnded::class);
    }
}
