<?php

namespace App\Support;

use App\Models\CallTask;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Runtime switch and small helpers for the new call flow (config/flow.php).
 *
 * Modes: legacy (old behaviour), new (everything through the new flow),
 * hybrid (new flow only for Estaff vacancies listed in FLOW_NEW_VACANCY_IDS).
 */
final class Flow
{
    public const MODE_LEGACY = 'legacy';

    public const MODE_NEW = 'new';

    public const MODE_HYBRID = 'hybrid';

    public static function mode(): string
    {
        $mode = (string) config('flow.mode', self::MODE_LEGACY);

        return in_array($mode, [self::MODE_NEW, self::MODE_HYBRID], true) ? $mode : self::MODE_LEGACY;
    }

    public static function isLegacy(): bool
    {
        return self::mode() === self::MODE_LEGACY;
    }

    public static function isNew(): bool
    {
        return self::mode() === self::MODE_NEW;
    }

    public static function isHybrid(): bool
    {
        return self::mode() === self::MODE_HYBRID;
    }

    /**
     * New-flow machinery (scheduler commands, location_id enrichment, interview schedules) is active.
     */
    public static function newFlowEnabled(): bool
    {
        return ! self::isLegacy();
    }

    /**
     * Whether a candidate of the given Estaff vacancy is handled by the new flow.
     */
    public static function usesNewFlow(int|string|null $vacancyId): bool
    {
        return match (self::mode()) {
            self::MODE_NEW => true,
            self::MODE_HYBRID => $vacancyId !== null && $vacancyId !== '' && (int) $vacancyId > 0
                && in_array((string) (int) $vacancyId, array_map('strval', (array) config('flow.new_vacancy_ids', [])), true),
            default => false,
        };
    }

    /**
     * Whether a Twin call belongs to a new-flow autoCall: by bot id, else by autoCall/task id stored in call_tasks.
     *
     * @param  array<int, string|null>  $twinIds  taskId / autoCallId values from the webhook
     */
    public static function isNewFlowCall(?string $botId, array $twinIds = []): bool
    {
        if (self::taskKeyByBot($botId) !== null) {
            return true;
        }

        $twinIds = array_values(array_filter(array_map(fn ($id) => is_scalar($id) ? (string) $id : null, $twinIds)));
        if ($twinIds === []) {
            return false;
        }

        $type = CallTask::whereIn('twin_id', $twinIds)->value('type');

        return is_string($type) && is_array(config("flow.tasks.$type"));
    }

    public static function timezone(): string
    {
        return (string) config('flow.timezone', 'UTC');
    }

    /**
     * Current moment in the business timezone.
     */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    /**
     * Calendar date (Y-m-d) in the business timezone.
     */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /**
     * @return array{name: string, bot: string, from: int, to: int, date_format: string}
     */
    public static function task(string $key): array
    {
        $task = config("flow.tasks.$key");

        if (! is_array($task) || empty($task['bot'])) {
            throw new \InvalidArgumentException("Flow task [$key] is not configured or has no bot id");
        }

        return $task;
    }

    /**
     * Resolve a task key by Twin bot id, or null when the bot is unknown.
     */
    public static function taskKeyByBot(?string $botId): ?string
    {
        if (empty($botId)) {
            return null;
        }

        foreach ((array) config('flow.tasks', []) as $key => $task) {
            if (! empty($task['bot']) && strcasecmp($task['bot'], $botId) === 0) {
                return $key;
            }
        }

        return null;
    }

    public static function taskName(string $key, CarbonInterface $date): string
    {
        $task = self::task($key);

        return str_replace('{date}', $date->format($task['date_format'] ?? 'd.m.Y'), $task['name']);
    }
}
