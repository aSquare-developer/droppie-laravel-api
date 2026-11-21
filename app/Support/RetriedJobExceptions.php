<?php

namespace App\Support;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Throwable;
use WeakMap;

/**
 * Remembers exceptions from job attempts that will be retried, so Sentry only hears about a
 * job once it has used up its retries. The queue worker fires JobExceptionOccurred after it
 * has decided whether the job failed for good and before it reports the exception.
 */
class RetriedJobExceptions
{
    /** @var WeakMap<Throwable, true>|null */
    private static ?WeakMap $retried = null;

    public static function remember(JobExceptionOccurred $event): void
    {
        if ($event->job->hasFailed()) {
            return;
        }

        self::$retried ??= new WeakMap;
        self::$retried[$event->exception] = true;
    }

    public static function willBeRetried(Throwable $exception): bool
    {
        return isset(self::$retried[$exception]);
    }
}
