<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleRouteService
{
    public function getDistanceInKm(
        string $startAddress,
        string $endAddress
    ): int {
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
}