<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleRouteService
{
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

        if (Cache::has($cacheKey)) {
            Log::info('Route distance cache hit', [
                'cache_key' => $cacheKey,
            ]);

            return Cache::get($cacheKey);
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
        $response = Http::withHeaders([
            'X-Goog-Api-Key' => config('services.google.routes_api_key'),
            'X-Goog-FieldMask' => 'routes.distanceMeters',
        ])->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
            'origin' => $this->waypoint($startAddress, $startPlaceId),
            'destination' => $this->waypoint($endAddress, $endPlaceId),
            'travelMode' => 'DRIVE',
        ]);

        $response->throw();

        $meters = $response->json('routes.0.distanceMeters');

        if (! $meters) {
            throw new \RuntimeException('Google Routes API did not return distance.');
        }

        return (float) round($meters / 1000, 1);
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
