<?php

namespace App\Services;

use App\Models\Route;
use App\Models\User;

use App\Jobs\CalculateRouteDistance;

class RouteService
{
    public function createRoute(User $user, array $data): Route 
    {
        $route = $user->routes()->create([
            ...$data,
            'distance_status' => 'pending',
        ]);

        CalculateRouteDistance::dispatch($route);

        return $route;
    }

    public function updateRoute(Route $route, array $data): Route 
    {
        $route->fill($data);

        $addressesChanged = $route->isDirty('start_address') || $route->isDirty('end_address');

        if ($addressesChanged) {
            $route->distance_km = null;
            $route->distance_status = 'pending';
            $route->distance_error = null;
        }

        $route->save();

        if ($addressesChanged) {
            CalculateRouteDistance::dispatch($route->fresh());
        }

        return $route->fresh();
    }

    public function deleteRoute(Route $route): void
    {
        $route->delete();
    }
}
