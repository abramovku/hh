<?php

namespace Tests\Unit\Flow;

use App\Jobs\Flow\ResolveVacancyAndRoute;
use App\Jobs\Flow\StartFlowCall;
use App\Jobs\StartTwinCall;
use App\Jobs\StartTwinColdConversation;
use App\Models\CallTask;
use App\Models\Response;
use App\Support\Flow;
use Illuminate\Support\Facades\Queue;
use Tests\Flow\FlowTestCase;

class FlowRouterTest extends FlowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['flow.mode' => 'hybrid']);
        Queue::fake();
    }

    private function webhook(string $stateId, int $candidateId = 555): array
    {
        return ['event_type' => 'candidate_state', 'data' => ['state_id' => $stateId, 'candidate_id' => $candidateId]];
    }

    public function test_uses_new_flow_depends_on_mode_and_list(): void
    {
        $this->assertTrue(Flow::usesNewFlow(self::NEW_VACANCY));
        $this->assertTrue(Flow::usesNewFlow((string) self::NEW_VACANCY));
        $this->assertFalse(Flow::usesNewFlow(self::LEGACY_VACANCY));
        $this->assertFalse(Flow::usesNewFlow(null));
        $this->assertFalse(Flow::usesNewFlow(0));

        config(['flow.mode' => 'new']);
        $this->assertTrue(Flow::usesNewFlow(null));

        config(['flow.mode' => 'legacy']);
        $this->assertFalse(Flow::usesNewFlow(self::NEW_VACANCY));

        config(['flow.mode' => 'garbage']);
        $this->assertSame('legacy', Flow::mode());
    }

    public function test_is_new_flow_call_by_bot_or_call_task(): void
    {
        CallTask::create(['date' => '2026-07-17', 'type' => 'reminder', 'twin_id' => 'rem-1']);
        CallTask::create(['date' => '2026-07-17', 'type' => 'Продавец-Кассир РФ', 'twin_id' => 'legacy-1']);

        $this->assertTrue(Flow::isNewFlowCall(config('flow.tasks.cold.bot')));
        $this->assertTrue(Flow::isNewFlowCall('unknown', ['rem-1']));
        $this->assertTrue(Flow::isNewFlowCall(null, [null, 'rem-1']));
        $this->assertFalse(Flow::isNewFlowCall('unknown', ['legacy-1']));
        $this->assertFalse(Flow::isNewFlowCall(null, []));
    }

    public function test_resolve_job_routes_to_new_flow_when_main_vacancy_is_listed(): void
    {
        $this->mockEstaff()->shouldReceive('getCandidate')->with(555, ['main_vacancy_id'])->once()
            ->andReturn(['candidate' => ['id' => 555, 'main_vacancy_id' => self::NEW_VACANCY]]);

        (new ResolveVacancyAndRoute($this->webhook('event_type_48')))->handle();

        Queue::assertPushed(StartFlowCall::class, 1);
        Queue::assertNotPushed(StartTwinColdConversation::class);
        Queue::assertNotPushed(ResolveVacancyAndRoute::class);
    }

    public function test_resolve_job_routes_to_legacy_when_main_vacancy_is_not_listed(): void
    {
        $this->mockEstaff()->shouldReceive('getCandidate')->once()
            ->andReturn(['candidate' => ['id' => 555, 'main_vacancy_id' => self::LEGACY_VACANCY]]);

        (new ResolveVacancyAndRoute($this->webhook('event_type_47')))->handle();

        Queue::assertPushed(StartTwinCall::class, 1);
        Queue::assertNotPushed(StartFlowCall::class);
    }

    public function test_resolve_job_falls_back_to_legacy_without_vacancy_or_on_estaff_error(): void
    {
        $estaff = $this->mockEstaff();
        $estaff->shouldReceive('getCandidate')->once()->andReturn(['candidate' => ['id' => 555]]);
        $estaff->shouldReceive('getCandidate')->once()->andThrow(new \Exception('estaff down'));

        (new ResolveVacancyAndRoute($this->webhook('event_type_47')))->handle();
        (new ResolveVacancyAndRoute($this->webhook('event_type_47')))->handle();

        Queue::assertPushed(StartTwinCall::class, 2);
        Queue::assertNotPushed(StartFlowCall::class);
        Queue::assertNotPushed(ResolveVacancyAndRoute::class);
    }

    public function test_estaff_sync_skips_new_flow_vacancies_in_hybrid_mode(): void
    {
        Response::create(['response_id' => 'r1', 'vacancy_id' => 111, 'manager_id' => 1]);
        Response::create(['response_id' => 'r2', 'vacancy_id' => 111, 'manager_id' => 1]);

        $estaff = $this->mockEstaff();
        $estaff->shouldReceive('findVacancy')->with(111)->once()->andReturn(['id' => self::NEW_VACANCY, 'user_id' => 7]);
        $estaff->shouldNotReceive('addResponse');

        $this->artisan('app:estaff-sync')->assertSuccessful();

        $this->assertSame(2, Response::where('error', 'new flow vacancy')->whereNull('sent_at')->count());
    }
}
