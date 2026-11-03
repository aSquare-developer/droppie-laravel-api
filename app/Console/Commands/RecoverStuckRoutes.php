<?php

namespace App\Console\Commands;

use App\Enums\DistanceStatus;
use App\Models\Route;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('routes:recover-stuck {--minutes=60 : Minutes without progress before a route is considered stuck}')]
#[Description('Mark routes stuck in pending or processing as failed so they can be edited or deleted again')]
class RecoverStuckRoutes extends Command
{
    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));

        $recovered = Route::query()
            ->whereIn('distance_status', [DistanceStatus::Pending, DistanceStatus::Processing])
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->update([
                'distance_status' => DistanceStatus::Failed,
                'distance_error' => 'Distance calculation timed out. Please try again.',
            ]);

        $this->info("Recovered {$recovered} stuck route(s).");

        return self::SUCCESS;
    }
}
