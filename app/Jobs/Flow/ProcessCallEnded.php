<?php

namespace App\Jobs\Flow;

use App\Services\Flow\CallResultProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Twin CALL_ENDED → CallResultProcessor. When Twin analyse has no session yet the job
 * re-dispatches itself with a delay (queue runs with --tries=1, so no release()).
 */
class ProcessCallEnded implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        private readonly array $webhook,
        private readonly int $attempt = 1,
    ) {}

    public function handle(): void
    {
        $result = app(CallResultProcessor::class)->process($this->webhook);

        if ($result !== CallResultProcessor::RETRY) {
            return;
        }

        $maxAttempts = max(1, (int) config('flow.analyse.max_attempts', 3));

        if ($this->attempt >= $maxAttempts) {
            Log::channel('app')->error('flow call ended: analyse session not found after all attempts', [
                'attempts' => $this->attempt, 'webhook' => $this->webhook,
            ]);

            return;
        }

        $delay = max(1, (int) config('flow.analyse.retry_delay', 60));
        Log::channel('twin')->info('flow call ended: retry scheduled', ['attempt' => $this->attempt + 1, 'delay' => $delay]);

        dispatch(new self($this->webhook, $this->attempt + 1))->delay(now()->addSeconds($delay));
    }
}
