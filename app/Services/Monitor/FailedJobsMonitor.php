<?php

namespace App\Services\Monitor;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FailedJobsMonitor
{
    private const SETTING_KEY = 'failed_jobs_notified_at';

    public function check(): void
    {
        $limit = (int) config('services.monitor.failed_jobs_limit', 5);
        $count = DB::table('failed_jobs')->count();

        if ($count <= $limit) {
            return;
        }

        if (! $this->cooldownPassed()) {
            return;
        }

        $appUrl = config('app.url');
        $message = "⚠️ *Failed Jobs Alert*\n\nApp: {$appUrl}\nFailed jobs: *{$count}* (limit: {$limit})";

        try {
            app('telegram')->send($message);
            Log::channel('app')->info('FailedJobsMonitor: alert sent', ['count' => $count, 'limit' => $limit]);
            $this->updateCooldown();
        } catch (\Exception $e) {
            Log::channel('app')->error('FailedJobsMonitor: send failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    private function cooldownPassed(): bool
    {
        $cooldownSeconds = (int) config('services.monitor.notify_cooldown', 3600);
        $setting = Setting::where('key', self::SETTING_KEY)->first();

        if (! $setting) {
            return true;
        }

        $lastNotified = Carbon::parse($setting->value);

        return $lastNotified->addSeconds($cooldownSeconds)->isPast();
    }

    private function updateCooldown(): void
    {
        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => now()->toDateTimeString()]
        );
    }
}
