<?php

namespace App\Jobs;

use App\Models\Route;
use App\Services\GoogleRouteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalculateRouteDistance implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(public Route $route)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(GoogleRouteService $googleRouteService): void
    {
        $this->route->loadMissing(['startAddress', 'endAddress']);

        if (! $this->route->startAddress || ! $this->route->endAddress) {
            throw new \RuntimeException('Route addresses are missing.');
        }

        $this->route->update([
            'distance_status' => 'processing',
            'distance_error' => null,
        ]);

        $distanceKm = $googleRouteService->getDistanceInKm(
            $this->route->startAddress->formatted_address,
            $this->route->endAddress->formatted_address,
            $this->route->startAddress->place_id,
            $this->route->endAddress->place_id,
        );

        $this->route->update([
            'distance_km' => $distanceKm,
            'distance_status' => 'completed',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->route->update([
            'distance_status' => 'failed',
            'distance_error' => $exception->getMessage(),
        ]);
    }
}
