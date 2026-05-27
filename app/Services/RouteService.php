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
        $route->update($data);

        return $route->fresh();
    }

    public function deleteRoute(Route $route): void
    {
        $route->delete();
    }
}