<?php

namespace Tests\Unit\Flow;

use App\Models\CallTask;
use App\Services\Twin\Twin;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Tests\Flow\FlowTestCase;

class TwinFlowMethodsTest extends FlowTestCase
{
    /** @var array<int, array{request: \GuzzleHttp\Psr7\Request}> */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Persisted token so TwinClient does not try to authenticate.
        DB::table('settings')->insert([
            'key' => 'twin_credentials',
            'value' => json_encode(['token' => 'tok', 'refreshToken' => 'ref']),
        ]);
    }

    private function twin(array $queue): Twin
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        $this->app->bind('GuzzleClient', function () use ($stack) {
            return function ($config = []) use ($stack) {
                return new Client(array_merge($config, ['handler' => $stack]));
            };
        });

        return new Twin(config('services.twin'));
    }

    private function jsonResponse(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    private function sentBody(int $index): array
    {
        return json_decode((string) $this->history[$index]['request']->getBody(), true);
    }

    public function test_get_auto_call_creates_task_with_spec_payload_and_stores_it(): void
    {
        $twin = $this->twin([$this->jsonResponse(['id' => ['identity' => 'ac-warm-1']])]);
        $date = CarbonImmutable::parse('2026-07-17 12:00', 'Europe/Moscow');

        $created = false;
        $this->assertSame('ac-warm-1', $twin->getAutoCall('warm', $date, $created));
        $this->assertTrue($created);

        $body = $this->sentBody(0);
        $this->assertSame(config('flow.urls.autocall'), (string) $this->history[0]['request']->getUri());
        $this->assertSame('ПК - Теплый отклик - 17.07.2026', $body['name']);
        $this->assertSame(config('flow.tasks.warm.bot'), $body['defaultExecData']);
        $this->assertTrue($body['additionalOptions']['recTrimLeft']);
        $this->assertTrue($body['additionalOptions']['useTr']);
        $this->assertSame(36000, $body['additionalOptions']['allowCallTimeFrom']);
        $this->assertSame(79200, $body['additionalOptions']['allowCallTimeTo']);
        $this->assertSame('provider-1', $body['additionalOptions']['providerId']);
        $this->assertSame('cid-1', $body['cidData']);
        $this->assertSame('manual', $body['startType']);
        $this->assertTrue($body['redialStrategyOptions']['redialStrategyEn']);

        $this->assertDatabaseHas('call_tasks', ['date' => '2026-07-17', 'type' => 'warm', 'twin_id' => 'ac-warm-1']);
    }

    public function test_get_auto_call_reuses_existing_task_for_same_date_and_type(): void
    {
        CallTask::create(['date' => '2026-07-17', 'type' => 'cold', 'twin_id' => 'ac-cold-existing']);
        $twin = $this->twin([]);

        $created = null;
        $this->assertSame('ac-cold-existing', $twin->getAutoCall('cold', CarbonImmutable::parse('2026-07-17', 'Europe/Moscow'), $created));
        $this->assertFalse($created);
        $this->assertCount(0, $this->history);
    }

    public function test_get_auto_call_uses_business_timezone_date(): void
    {
        $twin = $this->twin([$this->jsonResponse(['id' => ['identity' => 'ac-1']])]);
        // 22:30 UTC on the 16th is already the 17th in Moscow.
        $twin->getAutoCall('reminder', CarbonImmutable::parse('2026-07-16 22:30:00', 'UTC'));

        $this->assertSame('ПК_Напоминание 17.07', $this->sentBody(0)['name']);
        $this->assertSame(34200, $this->sentBody(0)['additionalOptions']['allowCallTimeFrom']);
        $this->assertDatabaseHas('call_tasks', ['date' => '2026-07-17', 'type' => 'reminder']);
    }

    public function test_get_auto_call_throws_when_twin_returns_no_id(): void
    {
        $twin = $this->twin([$this->jsonResponse(['error' => 'x'])]);

        $this->expectExceptionMessage('autoCall [warm] was not created');
        $twin->getAutoCall('warm');
        $this->assertDatabaseCount('call_tasks', 0);
    }

    public function test_add_candidate_payload_contains_client_external_id(): void
    {
        $twin = $this->twin([$this->jsonResponse(['id' => ['identity' => 'cand-1']])]);

        $twin->addCandidateToAutoCall('ac-1', '89001112233', '555', '79001112233');

        $this->assertSame(config('flow.urls.autocall_candidate'), (string) $this->history[0]['request']->getUri());
        $item = $this->sentBody(0);
        $this->assertArrayNotHasKey('batch', $item); // single-candidate endpoint: the wrapper caused HTTP 400
        $this->assertSame(['EStaffID' => '555'], $item['variables']);
        $this->assertSame(['EStaffID' => '555'], $item['callbackData']);
        $this->assertSame('ac-1', $item['autoCallId']);
        $this->assertSame(['89001112233'], $item['phone']);
        $this->assertSame('79001112233', $item['clientExternalId']);
        $this->assertTrue($item['forceStart']);
    }

    public function test_find_sessions_builds_query_from_spec(): void
    {
        $twin = $this->twin([$this->jsonResponse(['count' => 1, 'items' => [['results' => ['confirmation' => 'ПК_Лид']]]])]);

        $data = $twin->findSessions('79001112233', '2026-07-10T10:49:36+00:00');

        $this->assertSame('ПК_Лид', $data['items'][0]['results']['confirmation']);
        $uri = $this->history[0]['request']->getUri();
        $this->assertStringStartsWith(config('flow.urls.analyse_sessions'), (string) $uri);
        parse_str($uri->getQuery(), $query);
        $this->assertSame([
            'fields' => 'messagesAsString,results,currentStatusName',
            'limit' => '1',
            'from' => '2026-07-10T10:49:36+00:00',
            'phone' => '79001112233',
        ], $query);
        $this->assertSame('Bearer tok', $this->history[0]['request']->getHeaderLine('Authorization'));
    }
}
