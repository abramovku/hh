<?php

namespace Tests\Feature\Flow;

use App\Services\Location\Location;
use Mockery;
use Tests\Flow\FlowTestCase;

class EndpointLocationTest extends FlowTestCase
{
    private function mockLocation(?string $locationId, ?string $expectedPhone = null): void
    {
        $mock = Mockery::mock(Location::class);
        $expectation = $mock->shouldReceive('locationId');
        if ($expectedPhone !== null) {
            $expectation->with($expectedPhone);
        }
        $expectation->andReturn($locationId);
        $this->app->instance('location', $mock);
    }

    private function createPayload(string $phone): array
    {
        return [
            'candidate' => ['firstname' => 'Иван', 'mobile_phone' => $phone, 'email' => 'ivan@example.com'],
            'vacancy' => ['id' => 1],
        ];
    }

    public function test_create_candidate_gets_location_id_in_new_mode(): void
    {
        $this->mockLocation('Yug', '+7 900 111-22-33');
        $expected = $this->createPayload('+7 900 111-22-33');
        $expected['candidate']['location_id'] = 'Yug';
        $this->mockEstaff()->shouldReceive('addResponse')->once()->with($expected)->andReturn(['candidate' => ['id' => 9]]);

        $this->postJson('/api/twin/createCandidate', $this->createPayload('+7 900 111-22-33'))
            ->assertOk()->assertJsonPath('candidate.id', 9);
    }

    public function test_create_candidate_without_region_match_is_sent_unchanged(): void
    {
        $this->mockLocation(null);
        $this->mockEstaff()->shouldReceive('addResponse')->once()->with($this->createPayload('79001112233'))->andReturn([]);

        $this->postJson('/api/twin/createCandidate', $this->createPayload('79001112233'))->assertOk();
    }

    public function test_update_candidate_reads_phone_from_estaff_when_not_in_changed_data(): void
    {
        $this->mockLocation('Centr', '79001112233');
        $estaff = $this->mockEstaff();
        $estaff->shouldReceive('getCandidate')->with(5)->once()->andReturn(['candidate' => ['mobile_phone' => '79001112233']]);
        $estaff->shouldReceive('changeCandidate')->once()->with([
            'candidate' => ['id' => 5],
            'changed_data' => ['lastname' => 'Петров', 'location_id' => 'Centr'],
        ])->andReturn([]);

        $this->postJson('/api/twin/updateCandidate', [
            'candidate' => ['id' => 5],
            'changed_data' => ['lastname' => 'Петров'],
        ])->assertOk();
    }

    public function test_legacy_mode_does_not_touch_payload(): void
    {
        config(['flow.mode' => 'legacy']);
        $this->app->instance('location', Mockery::mock(Location::class)->shouldNotReceive('locationId')->getMock());
        $this->mockEstaff()->shouldReceive('addResponse')->once()->with($this->createPayload('79001112233'))->andReturn([]);

        $this->postJson('/api/twin/createCandidate', $this->createPayload('79001112233'))->assertOk();
    }
}
