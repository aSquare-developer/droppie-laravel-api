<?php

namespace App\Services;

use App\Mail\FailureAlert;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the administrator when background work fails, at most once per kind of failure
 * within the throttle window, so an outage does not flood the inbox.
 */
class FailureAlerter
{
    public function jobFailed(JobFailed $event): void
    {
        $this->alert(
            $event->job->resolveName(),
            $event->exception,
            ['connection' => $event->connectionName, 'queue' => $event->job->getQueue()],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function alert(string $source, Throwable $exception, array $context = []): void
    {
        $email = config('services.alerts.email');

        if (blank($email)) {
            return;
        }

        $key = 'failure-alert:'.sha1($source.'|'.$exception::class);
        $minutes = max(1, (int) config('services.alerts.throttle_minutes', 15));

        try {
            // Cache::add() only succeeds for the first failure in the window.
            if (! Cache::add($key, true, now()->addMinutes($minutes))) {
                // Some cache stores only increment existing keys, so create the counter first.
                Cache::add($key.':suppressed', 0, now()->addDay());
                Cache::increment($key.':suppressed');

                return;
            }

            $suppressed = (int) Cache::pull($key.':suppressed', 0);

            Mail::to($email)->send(new FailureAlert($source, $exception, $context, $suppressed, $minutes));
        } catch (Throwable $alertException) {
            // A broken mailer or cache must never take the queue worker down with it.
            Log::error('Could not send the failure alert.', [
                'source' => $source,
                'exception' => $alertException->getMessage(),
            ]);
        }
    }
}
