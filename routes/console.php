<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Overlap locks expire quickly so a killed process cannot block its task for the default 24 hours.
// sentryMonitor() sends Sentry Cron check-ins (only with a DSN), so a stopped cron shows up as missed.
Schedule::command('routes:recover-stuck')
    ->everyTenMinutes()
    ->withoutOverlapping(15)
    ->sentryMonitor(checkInMargin: 5, maxRuntime: 5);

// A short-lived worker started every minute by the scheduler, so no process manager is needed.
// The host's cron sometimes skips a minute, so only three missed check-ins in a row open an issue.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->sentryMonitor(checkInMargin: 2, maxRuntime: 3, failureIssueThreshold: 3);

// Expired API tokens and old failed jobs are never removed otherwise, so these tables would only grow.
Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('03:17')
    ->sentryMonitor(checkInMargin: 30, maxRuntime: 10);
Schedule::command('queue:prune-failed --hours=720')
    ->dailyAt('03:27')
    ->sentryMonitor(checkInMargin: 30, maxRuntime: 10);
