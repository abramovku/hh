<?php

namespace App\Services\Flow;

/**
 * Outcome of the duplicate / position check (ТЗ 3.1).
 */
final class GuardResult
{
    /**
     * @param  int  $candidateId  Candidate selected for further processing (index 0 of the Estaff answer).
     * @param  string  $phone  Sanitized phone (digits, may keep leading 8/7 as stored in Estaff).
     * @param  array<int, int>  $duplicates  Ids of the other candidates sharing the phone.
     * @param  array<int, string|null>  $positions  vacancy_id => position_id for every found candidate.
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly int $candidateId,
        public readonly string $phone,
        public readonly ?int $mainVacancyId = null,
        public readonly array $duplicates = [],
        public readonly array $positions = [],
        public readonly ?string $reason = null,
    ) {}

    public static function denied(int $candidateId, string $reason, string $phone = '', array $duplicates = [], array $positions = []): self
    {
        return new self(false, $candidateId, $phone, null, $duplicates, $positions, $reason);
    }

    public function hasDuplicates(): bool
    {
        return $this->duplicates !== [];
    }
}
