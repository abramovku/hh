<?php

namespace Tests\Unit\Flow;

use App\Services\Estaff\Estaff;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\Flow\FlowTestCase;

class EstaffFlowMethodsTest extends FlowTestCase
{
    /** @var array<int, array{request: \GuzzleHttp\Psr7\Request}> */
    private array $history = [];

    private function estaff(array $queue): Estaff
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        $this->app->bind('GuzzleClient', function () use ($stack) {
            return function ($config = []) use ($stack) {
                return new Client(array_merge($config, ['handler' => $stack]));
            };
        });

        return new Estaff(['url' => 'https://estaff.test/api/', 'token' => 'tok']);
    }

    private function jsonResponse(array $data): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($data));
    }

    private function sentBody(int $index): array
    {
        return json_decode((string) $this->history[$index]['request']->getBody(), true);
    }

    public function test_find_candidates_by_phone_uses_documented_request_and_response(): void
    {
        $estaff = $this->estaff([$this->jsonResponse(['candidates' => [['id' => 1, 'main_vacancy_id' => 100], ['id' => 2, 'main_vacancy_id' => 200]], 'success' => true])]);

        $found = $estaff->findCandidatesByPhone('79001112233');

        $this->assertSame('https://estaff.test/api/candidate/find', (string) $this->history[0]['request']->getUri());
        $this->assertSame(['filter' => ['mobile_phone' => '79001112233'], 'field_names' => ['id', 'main_vacancy_id']], $this->sentBody(0));
        $this->assertSame([1, 2], array_column($found, 'id'));
    }

    public function test_candidate_state_requests_state_and_events_only_by_default(): void
    {
        $estaff = $this->estaff([$this->jsonResponse(['candidate' => ['id' => 5, 'state_id' => 'event_type_35', 'events' => []]])]);

        $state = $estaff->getCandidateState(5);

        $this->assertSame(['state' => 'event_type_35', 'state_date' => null], $state);
        $this->assertSame(['mobile_phone', 'state_id', 'events'], $this->sentBody(0)['field_names']);
    }

    public function test_candidate_state_uses_configured_date_field_only_without_matching_event(): void
    {
        config(['flow.estaff_state_fields.state_date' => 'state_date']);
        $estaff = $this->estaff([
            $this->jsonResponse(['candidate' => ['id' => 5, 'state_id' => 'event_type_49:scheduled', 'state_date' => '2026-07-10T10:00:00+03:00', 'events' => []]]),
            $this->jsonResponse(['candidate' => ['id' => 5, 'state_id' => 'event_type_49:scheduled', 'state_date' => '2026-07-10T10:00:00+03:00', 'events' => [
                ['date' => '2026-07-17T10:00:00+03:00', 'type_id' => 'event_type_49', 'occurrence_id' => 'scheduled', 'vacancy_id' => 1],
            ]]]),
        ]);

        $this->assertSame('2026-07-10T10:00:00+03:00', $estaff->getCandidateState(5)['state_date']);
        $this->assertSame(['mobile_phone', 'state_id', 'state_date', 'events'], $this->sentBody(0)['field_names']);

        // The scheduled event date (interview date) wins over the transition date.
        $this->assertSame('2026-07-17T10:00:00+03:00', $estaff->getCandidateState(5)['state_date']);
    }

    public function test_candidate_state_date_comes_from_latest_matching_event(): void
    {
        $estaff = $this->estaff([$this->jsonResponse(['candidate' => [
            'id' => 5,
            'state_id' => 'event_type_49:scheduled',
            'events' => [
                ['date' => '2026-07-10T09:00:00+03:00', 'type_id' => 'event_type_49', 'occurrence_id' => 'scheduled', 'vacancy_id' => 1],
                ['date' => '2026-07-17T10:00:00+03:00', 'type_id' => 'event_type_49', 'occurrence_id' => 'scheduled', 'vacancy_id' => 1],
                ['date' => '2026-07-20T10:00:00+03:00', 'type_id' => 'event_type_88', 'occurrence_id' => '', 'vacancy_id' => 1],
                ['date' => '2026-07-21T10:00:00+03:00', 'type_id' => 'event_type_49', 'occurrence_id' => 'other', 'vacancy_id' => 1],
            ],
        ]])]);

        $state = $estaff->getCandidateState(5);

        $this->assertSame('event_type_49:scheduled', $state['state']);
        $this->assertSame('2026-07-17T10:00:00+03:00', $state['state_date']);
    }

    public function test_candidate_state_without_fields_returns_nulls(): void
    {
        $estaff = $this->estaff([$this->jsonResponse(['candidate' => ['id' => 5, 'mobile_phone' => '79001112233']])]);

        $this->assertSame(['state' => null, 'state_date' => null], $estaff->getCandidateState(5));
    }

    public function test_update_candidate_location_sends_changed_data(): void
    {
        $estaff = $this->estaff([$this->jsonResponse(['success' => true])]);

        $estaff->updateCandidateLocation(5, 'Yug');

        $this->assertSame('https://estaff.test/api/candidate/change', (string) $this->history[0]['request']->getUri());
        $this->assertSame(['candidate' => ['id' => 5], 'changed_data' => ['location_id' => 'Yug']], $this->sentBody(0));
    }

    public function test_vacancy_position_id_requests_the_field(): void
    {
        $estaff = $this->estaff([$this->jsonResponse(['vacancy' => ['id' => 100, 'position_id' => 'pos-seller']])]);

        $this->assertSame('pos-seller', $estaff->getVacancyPositionId(100));
        $this->assertContains('position_id', $this->sentBody(0)['field_names']);
    }
}
