<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Runtime switch and small helpers for the new call flow (config/flow.php).
 */
final class Flow
{
    public const MODE_LEGACY = 'legacy';

    public const MODE_NEW = 'new';

    public static function isNew(): bool
    {
        return config('flow.mode') === self::MODE_NEW;
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
