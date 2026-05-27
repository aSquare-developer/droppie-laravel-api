<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Log;

class GoogleRouteService
{
    public function getDistanceInKm(string $startAddress,string $endAddress): int 
    {

        $cacheKey = $this->makeDistanceCacheKey(
            $startAddress,
            $endAddress
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
            $endAddress
        );

        Cache::put($cacheKey, $distanceKm, now()->addDays(7));

        return $distanceKm;
    }

    private function fetchDistanceFromGoogle(string $startAddress, string $endAddress): int 
    {
        $response = Http::withHeaders([
            'X-Goog-Api-Key' => config('services.google.routes_api_key'),
            'X-Goog-FieldMask' => 'routes.distanceMeters',
        ])->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
            'origin' => [
                'address' => $startAddress,
            ],
            'destination' => [
                'address' => $endAddress,
            ],
            'travelMode' => 'DRIVE',
        ]);

        $response->throw();

        $meters = $response->json('routes.0.distanceMeters');

        if (! $meters) {
            throw new \RuntimeException('Google Routes API did not return distance.');
        }

        return (int) round($meters / 1000);
    }

    private function makeDistanceCacheKey(string $startAddress, string $endAddress): string 
    {
        $start = Str::of($startAddress)
            ->lower()
            ->squish()
            ->toString();

        $end = Str::of($endAddress)
            ->lower()
            ->squish()
            ->toString();

        return 'routes:distance:' . sha1($start . '|' . $end);
    }
}