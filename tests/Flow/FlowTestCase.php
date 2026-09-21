<?php

namespace Tests\Flow;

use App\Services\Estaff\Estaff;
use App\Services\Twin\Twin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Shared setup for the new flow tests: DB-backed log channels are silenced,
 * flow mode is `new`, allowed positions are configured, and the Estaff / Twin
 * singletons can be replaced with mocks.
 */
abstract class FlowTestCase extends TestCase
{
    use RefreshDatabase;

    protected const ALLOWED_POSITION = 'pos-seller';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['app', 'hh', 'twin', 'estaff', 'location'] as $channel) {
            config(["logging.channels.$channel" => ['driver' => 'null']]);
        }

        config([
            'flow.mode' => 'new',
            'flow.timezone' => 'Europe/Moscow',
            'flow.allowed_position_ids' => [self::ALLOWED_POSITION],
            'flow.old_script_vacancy_ids' => ['7541291626956944847'],
            'flow.sleep_after_create' => 0,
            'services.twin.provider_id' => 'provider-1',
            'services.twin.cid' => 'cid-1',
        ]);
    }

    protected function mockEstaff(): MockInterface
    {
        $mock = Mockery::mock(Estaff::class);
        $this->app->instance('estaff', $mock);

        return $mock;
    }

    protected function mockTwin(): MockInterface
    {
        $mock = Mockery::mock(Twin::class);
        $this->app->instance('twin', $mock);

        return $mock;
    }

    /**
     * Minimal Twin CALL_ENDED webhook payload (ТЗ 5.1).
     */
    protected function callEnded(array $overrides = []): array
    {
        return array_replace([
            'event' => 'CALL_ENDED',
            'type' => 'OUTGOING',
            'botId' => config('flow.tasks.warm.bot'),
            'id' => 'call-1',
            'taskId' => 'task-1',
            'status' => 'ANSWERED',
            'callFrom' => '74992868344',
            'callTo' => '79001112233',
            'startedAt' => '2026-07-10T10:49:36+00:00',
            'variables' => [],
            'result' => [],
            'callbackData' => ['EStaffID' => '555'],
        ], $overrides);
    }
}
