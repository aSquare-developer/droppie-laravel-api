<?php

namespace App\Services;

use App\Models\Route;
use App\Models\User;

class RouteService
{
    public function createRoute(User $user, array $data): Route 
    {
        return $user->routes()->create($data);
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