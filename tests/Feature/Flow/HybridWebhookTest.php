<?php

namespace Tests\Feature\Flow;

use App\Jobs\Flow\ProcessCallEnded;
use App\Jobs\Flow\ProcessNewCandidate;
use App\Jobs\Flow\ResolveVacancyAndRoute;
use App\Jobs\Flow\StartFlowCall;
use App\Jobs\OperateTwinVoiceWebhook;
use App\Jobs\StartTwinCall;
use App\Jobs\StartTwinColdConversation;
use App\Jobs\StartTwinManualConversation;
use App\Models\CallTask;
use App\Models\InterviewSchedule;
use Illuminate\Support\Facades\Queue;
use Tests\Flow\FlowTestCase;

class HybridWebhookTest extends FlowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['flow.mode' => 'hybrid']);
        Queue::fake();
    }

    private function estaffWebhook(string $stateId, ?int $vacancyId, int $candidateId = 555): array
    {
        $data = ['state_id' => $stateId, 'candidate_id' => $candidateId];
        if ($vacancyId !== null) {
            $data['vacancy_id'] = $vacancyId;
        }

        return ['event_type' => 'candidate_state', 'data' => $data];
    }

    public function test_states_47_and_48_go_to_new_flow_for_listed_vacancy(): void
    {
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_47', self::NEW_VACANCY))->assertOk();
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_48', self::NEW_VACANCY))->assertOk();

        Queue::assertPushed(StartFlowCall::class, 2);
        Queue::assertNotPushed(StartTwinCall::class);
        Queue::assertNotPushed(StartTwinColdConversation::class);
    }

    public function test_states_47_and_48_go_to_legacy_for_other_vacancy(): void
    {
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_47', self::LEGACY_VACANCY))->assertOk();
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_48', self::LEGACY_VACANCY))->assertOk();

        Queue::assertPushed(StartTwinCall::class, 1);
        Queue::assertPushed(StartTwinColdConversation::class, 1);
        Queue::assertNotPushed(StartFlowCall::class);
    }

    public function test_missing_vacancy_id_is_resolved_from_estaff_in_a_job(): void
    {
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_47', null))->assertOk();

        Queue::assertPushed(ResolveVacancyAndRoute::class, 1);
        Queue::assertNotPushed(StartTwinCall::class);
        Queue::assertNotPushed(StartFlowCall::class);
    }

    public function test_new_state_only_matters_for_listed_vacancy(): void
    {
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('new', self::NEW_VACANCY))->assertOk();
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('new', self::LEGACY_VACANCY))->assertOk();

        Queue::assertPushed(ProcessNewCandidate::class, 1);
    }

    public function test_legacy_only_state_goes_to_legacy_regardless_of_vacancy(): void
    {
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_32', self::NEW_VACANCY))->assertOk();
        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_32', null))->assertOk();

        Queue::assertPushed(StartTwinManualConversation::class, 1); // the one without vacancy_id is dropped by legacy itself
        Queue::assertNotPushed(ResolveVacancyAndRoute::class);
    }

    public function test_other_states_cancel_new_flow_schedules_and_dispatch_nothing(): void
    {
        InterviewSchedule::create(['candidate_id' => 555, 'phone' => '79001112233', 'interview_date' => '2026-07-17', 'stage' => InterviewSchedule::STAGE_SCHEDULED]);

        $this->postJson('/api/estaff-webhooks', $this->estaffWebhook('event_type_46', null))->assertOk();

        $this->assertDatabaseHas('interview_schedules', ['candidate_id' => 555, 'stage' => InterviewSchedule::STAGE_CANCELLED]);
        Queue::assertNothingPushed();
    }

    public function test_call_ended_from_new_flow_bot_is_processed(): void
    {
        $this->postJson('/api/twin-webhooks-voice', $this->callEnded())->assertOk();

        Queue::assertPushed(ProcessCallEnded::class, 1);
        Queue::assertNotPushed(OperateTwinVoiceWebhook::class);
    }

    public function test_call_ended_from_legacy_bot_is_ignored(): void
    {
        $this->postJson('/api/twin-webhooks-voice', $this->callEnded(['botId' => 'legacy-bot', 'taskId' => 'legacy-task']))->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_candidate_changed_routes_by_call_task_type(): void
    {
        CallTask::create(['date' => '2026-07-17', 'type' => 'warm', 'twin_id' => 'new-task']);
        CallTask::create(['date' => '2026-07-17', 'type' => 'Продавец-Кассир РФ', 'twin_id' => 'legacy-task']);

        $this->postJson('/api/twin-webhooks-voice', ['event' => 'CANDIDATE_CHANGED', 'taskId' => 'new-task', 'lastCallId' => 'c1'])->assertOk();
        Queue::assertNotPushed(OperateTwinVoiceWebhook::class);

        $this->postJson('/api/twin-webhooks-voice', ['event' => 'CANDIDATE_CHANGED', 'taskId' => 'legacy-task', 'lastCallId' => 'c2'])->assertOk();
        $this->postJson('/api/twin-webhooks-voice', ['event' => 'CANDIDATE_CHANGED', 'taskId' => 'unknown-task', 'lastCallId' => 'c3'])->assertOk();
        Queue::assertPushed(OperateTwinVoiceWebhook::class, 2);
        Queue::assertNotPushed(ProcessCallEnded::class);
    }

    public function test_candidate_changed_without_task_id_is_rejected_like_legacy(): void
    {
        $this->postJson('/api/twin-webhooks-voice', ['event' => 'CANDIDATE_CHANGED'])->assertStatus(422);
    }
}
