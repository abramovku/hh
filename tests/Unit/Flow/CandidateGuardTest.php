<?php

namespace Tests\Unit\Flow;

use App\Services\Flow\CandidateGuard;
use Tests\Flow\FlowTestCase;

class CandidateGuardTest extends FlowTestCase
{
    private function estaffWithCandidate(int $id, string $phone = '+7 (900) 111-22-33', int $vacancy = 100)
    {
        $estaff = $this->mockEstaff();
        $estaff->shouldReceive('getCandidate')->with($id, ['main_vacancy_id'])->once()
            ->andReturn(['candidate' => ['id' => $id, 'mobile_phone' => $phone, 'main_vacancy_id' => $vacancy]]);

        return $estaff;
    }

    public function test_single_allowed_candidate_is_processed(): void
    {
        $estaff = $this->estaffWithCandidate(1);
        $estaff->shouldReceive('findCandidatesByPhone')->with('+7 (900) 111-22-33')->once()
            ->andReturn([['id' => 1, 'main_vacancy_id' => 100]]);
        $estaff->shouldReceive('getVacancyPositionId')->with(100)->once()->andReturn(self::ALLOWED_POSITION);

        $result = app(CandidateGuard::class)->check(1);

        $this->assertTrue($result->allowed);
        $this->assertSame(1, $result->candidateId);
        $this->assertSame('79001112233', $result->phone);
        $this->assertSame(100, $result->mainVacancyId);
        $this->assertSame([], $result->duplicates);
    }

    public function test_stops_when_no_candidate_has_allowed_position(): void
    {
        $estaff = $this->estaffWithCandidate(1);
        $estaff->shouldReceive('findCandidatesByPhone')->once()->andReturn([['id' => 1, 'main_vacancy_id' => 100]]);
        $estaff->shouldReceive('getVacancyPositionId')->with(100)->once()->andReturn('pos-other');

        $result = app(CandidateGuard::class)->check(1);

        $this->assertFalse($result->allowed);
        $this->assertSame('position not allowed', $result->reason);
    }

    public function test_first_candidate_of_answer_is_selected_and_duplicates_reported(): void
    {
        $estaff = $this->estaffWithCandidate(3);
        $estaff->shouldReceive('findCandidatesByPhone')->once()->andReturn([
            ['id' => 1, 'main_vacancy_id' => 100],
            ['id' => 2, 'main_vacancy_id' => 200],
            ['id' => 3, 'main_vacancy_id' => 100],
        ]);
        // Same vacancy is resolved once (cached).
        $estaff->shouldReceive('getVacancyPositionId')->with(100)->once()->andReturn(self::ALLOWED_POSITION);
        $estaff->shouldReceive('getVacancyPositionId')->with(200)->once()->andReturn('pos-other');

        $result = app(CandidateGuard::class)->check(3);

        $this->assertTrue($result->allowed);
        $this->assertSame(1, $result->candidateId);
        $this->assertSame([2, 3], $result->duplicates);
        $this->assertSame([100 => self::ALLOWED_POSITION, 200 => 'pos-other'], $result->positions);
    }

    public function test_falls_back_to_normalized_phone_and_to_webhook_candidate(): void
    {
        $estaff = $this->estaffWithCandidate(7, '8 900 111-22-33');
        $estaff->shouldReceive('findCandidatesByPhone')->with('8 900 111-22-33')->once()->andReturn([]);
        $estaff->shouldReceive('findCandidatesByPhone')->with('89001112233')->once()->andReturn([]);
        $estaff->shouldReceive('findCandidatesByPhone')->with('79001112233')->once()->andReturn([]);
        $estaff->shouldReceive('getVacancyPositionId')->with(100)->once()->andReturn(self::ALLOWED_POSITION);

        $result = app(CandidateGuard::class)->check(7);

        $this->assertTrue($result->allowed);
        $this->assertSame(7, $result->candidateId);
    }

    public function test_no_phone_denies(): void
    {
        $this->estaffWithCandidate(9, '');

        $result = app(CandidateGuard::class)->check(9);

        $this->assertFalse($result->allowed);
        $this->assertSame('no mobile phone', $result->reason);
    }

    public function test_missing_allowed_positions_config_denies_without_estaff_calls(): void
    {
        config(['flow.allowed_position_ids' => []]);
        $this->mockEstaff()->shouldNotReceive('getCandidate');

        $result = app(CandidateGuard::class)->check(1);

        $this->assertFalse($result->allowed);
        $this->assertSame('allowed_position_ids not configured', $result->reason);
    }
}
