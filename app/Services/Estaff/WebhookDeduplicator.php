<?php

namespace App\Services\Estaff;

use Illuminate\Support\Facades\Cache;

/**
 * Estaff re-sends the same candidate_state webhook (card re-saved, retries, several
 * subscriptions). The first copy is remembered in the cache for
 * config('services.estaff.webhook_dedup_ttl') seconds; later copies are ignored.
 */
class WebhookDeduplicator
{
    public const KEY_PREFIX = 'estaff:webhook:';

    /**
     * @return bool true when an identical webhook was already accepted within the TTL
     */
    public function isDuplicate(array $data): bool
    {
        $ttl = $this->ttl();
        $key = $this->key($data);

        if ($ttl <= 0 || $key === null) {
            return false;
        }

        return ! Cache::add($key, now()->toDateTimeString(), $ttl);
    }

    /**
     * When the first copy of this webhook was accepted (for logging).
     */
    public function firstSeenAt(array $data): ?string
    {
        $key = $this->key($data);

        return $key === null ? null : Cache::get($key);
    }

    public function ttl(): int
    {
        return (int) config('services.estaff.webhook_dedup_ttl', 0);
    }

    /**
     * Only candidate state changes are deduplicated: event type + candidate + state + vacancy.
     * Test pings and webhooks without a candidate are always let through.
     */
    public function key(array $data): ?string
    {
        $candidateId = (int) ($data['data']['candidate_id'] ?? 0);
        $stateId = (string) ($data['data']['state_id'] ?? '');
        $eventType = (string) ($data['event_type'] ?? '');

        if ($eventType === '' || $candidateId <= 0 || $stateId === '') {
            return null;
        }

        $vacancyId = (string) ($data['data']['vacancy_id'] ?? '');

        return self::KEY_PREFIX.$eventType.':'.$candidateId.':'.$stateId.':'.$vacancyId;
    }
}
