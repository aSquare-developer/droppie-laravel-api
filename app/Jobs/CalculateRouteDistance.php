<?php

namespace App\Jobs;

use App\Exceptions\GoogleRouteException;
use App\Models\Route;
use App\Services\GoogleRouteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CalculateRouteDistance implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

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

        try {
            $distanceKm = $googleRouteService->getDistanceInKm(
                $this->route->startAddress->formatted_address,
                $this->route->endAddress->formatted_address,
                $this->route->startAddress->place_id,
                $this->route->endAddress->place_id,
            );
        } catch (GoogleRouteException $exception) {
            if ($exception->isRetryable()) {
                throw $exception;
            }

            $this->failed($exception);

            return;
        }

        $this->route->update([
            'distance_km' => $distanceKm,
            'distance_status' => 'completed',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Route distance job failed', [
            'route_id' => $this->route->id,
            'exception' => $exception->getMessage(),
            'exception_class' => $exception::class,
        ]);

        $this->route->update([
            'distance_status' => 'failed',
            'distance_error' => $exception instanceof GoogleRouteException
                ? $exception->getMessage()
                : 'Unable to calculate route distance.',
        ]);
    }
}
