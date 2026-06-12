<?php

namespace App\Services;

use App\Exceptions\GoogleRouteException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleRouteService
{
    private const ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public function getDistanceInKm(
        string $startAddress,
        string $endAddress,
        ?string $startPlaceId = null,
        ?string $endPlaceId = null
    ): float {

        $cacheKey = $this->makeDistanceCacheKey(
            $startAddress,
            $endAddress,
            $startPlaceId,
            $endPlaceId
        );

        $cachedDistance = Cache::get($cacheKey);

        if ($cachedDistance !== null) {
            Log::info('Route distance cache hit', [
                'cache_key' => $cacheKey,
            ]);

            return (float) $cachedDistance;
        }

        Log::info('Route distance cache miss', [
            'cache_key' => $cacheKey,
        ]);

        $distanceKm = $this->fetchDistanceFromGoogle(
            $startAddress,
            $endAddress,
            $startPlaceId,
            $endPlaceId
        );

        Cache::put($cacheKey, $distanceKm, now()->addDays(7));

        return $distanceKm;
    }

    private function fetchDistanceFromGoogle(
        string $startAddress,
        string $endAddress,
        ?string $startPlaceId = null,
        ?string $endPlaceId = null
    ): float {
        $apiKey = config('services.google.routes_api_key');

        if (blank($apiKey)) {
            throw new GoogleRouteException(
                GoogleRouteException::CONFIGURATION,
                'Route distance calculation is not configured.'
            );
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'routes.distanceMeters',
            ])
                ->connectTimeout((float) config('services.google.routes_connect_timeout', 3))
                ->timeout((float) config('services.google.routes_timeout', 10))
                ->retry(
                    max(1, (int) config('services.google.routes_max_attempts', 3)),
                    fn (int $attempt): int => min(
                        (int) config('services.google.routes_retry_delay_ms', 250) * (2 ** ($attempt - 1)),
                        2000
                    ),
                    fn (Throwable $exception): bool => $this->shouldRetry($exception)
                )
                ->post(self::ENDPOINT, [
                    'origin' => $this->waypoint($startAddress, $startPlaceId),
                    'destination' => $this->waypoint($endAddress, $endPlaceId),
                    'travelMode' => 'DRIVE',
                ]);
        } catch (ConnectionException $exception) {
            $this->logFailure(GoogleRouteException::UNAVAILABLE, null, $exception);

            throw new GoogleRouteException(
                GoogleRouteException::UNAVAILABLE,
                'Google Routes is temporarily unavailable. Distance calculation could not be completed.',
                $exception
            );
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            $reason = $this->isTransientStatus($status)
                ? GoogleRouteException::UNAVAILABLE
                : GoogleRouteException::REQUEST_REJECTED;

            $this->logFailure($reason, $status, $exception);

            throw new GoogleRouteException(
                $reason,
                $reason === GoogleRouteException::UNAVAILABLE
                    ? 'Google Routes is temporarily unavailable. Distance calculation could not be completed.'
                    : 'Google Routes could not calculate the distance for the selected addresses.',
                $exception
            );
        }

        $meters = $response->json('routes.0.distanceMeters');

        if (! is_numeric($meters)) {
            $this->logFailure(GoogleRouteException::NO_ROUTE, $response->status());

            throw new GoogleRouteException(
                GoogleRouteException::NO_ROUTE,
                'No drivable route was found between the selected addresses.'
            );
        }

        return (float) round($meters / 1000, 1);
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && $this->isTransientStatus($exception->response->status());
    }

    private function isTransientStatus(int $status): bool
    {
        return in_array($status, [408, 429], true) || $status >= 500;
    }

    private function logFailure(string $reason, ?int $status, ?Throwable $exception = null): void
    {
        Log::error('Google Routes distance calculation failed', [
            'reason' => $reason,
            'status' => $status,
            'exception' => $exception?->getMessage(),
        ]);
    }

    private function waypoint(string $address, ?string $placeId): array
    {
        if (filled($placeId)) {
            return [
                'placeId' => $placeId,
            ];
        }

        return [
            'address' => $address,
        ];
    }

    private function makeDistanceCacheKey(
        string $startAddress,
        string $endAddress,
        ?string $startPlaceId = null,
        ?string $endPlaceId = null
    ): string {
        $start = Str::of($startAddress)
            ->lower()
            ->squish()
            ->toString();

        $end = Str::of($endAddress)
            ->lower()
            ->squish()
            ->toString();

        return 'routes:distance:'.sha1(($startPlaceId ?: $start).'|'.($endPlaceId ?: $end));
    }
}
