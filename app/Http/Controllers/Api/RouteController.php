<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRouteRequest;
use App\Http\Requests\UpdateRouteRequest;
use App\Http\Resources\RouteResource;
use App\Models\Route;
use App\Services\RouteService;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $query = $request->user()
            ->routes()
            ->with(['startAddress', 'endAddress']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q
                    ->whereHas('startAddress', function ($addressQuery) use ($request) {
                        $addressQuery
                            ->where('formatted_address', 'like', '%'.$request->search.'%')
                            ->orWhere('city', 'like', '%'.$request->search.'%')
                            ->orWhere('postal_code', 'like', '%'.$request->search.'%');
                    })
                    ->orWhereHas('endAddress', function ($addressQuery) use ($request) {
                        $addressQuery
                            ->where('formatted_address', 'like', '%'.$request->search.'%')
                            ->orWhere('city', 'like', '%'.$request->search.'%')
                            ->orWhere('postal_code', 'like', '%'.$request->search.'%');
                    });
            });
        }

        if ($request->filled('min_distance')) {
            $query->where('distance_km', '>=', $request->min_distance);
        }

        if ($request->filled('max_distance')) {
            $query->where('distance_km', '<=', $request->max_distance);
        }

        $totalDistanceKm = (float) (clone $query)->sum('distance_km');

        $sort = $request->get('sort', '-created_at');

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        $allowedSorts = [
            'created_at',
            'distance_km',
            'started_at',
        ];

        if (! in_array($column, $allowedSorts, true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        $routes = $query
            ->orderBy($column, $direction)
            ->paginate(10)
            ->withQueryString();

        return RouteResource::collection($routes)->additional([
            'summary' => [
                'total_distance_km' => round($totalDistanceKm, 1),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRouteRequest $request, RouteService $routeService)
    {
        $route = $routeService->createRoute($request->user(), $request->validated());

        return response()->json([
            'message' => 'Route created',
            'data' => new RouteResource($route),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Route $route)
    {

        $this->authorize('view', $route);

        $route->loadMissing(['startAddress', 'endAddress']);

        return response()->json([
            'data' => new RouteResource($route),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRouteRequest $request, Route $route, RouteService $routeService)
    {

        $this->authorize('update', $route);

        $route = $routeService->updateRoute($route, $request->validated());

        return response()->json([
            'message' => 'Route updated successfully',
            'data' => new RouteResource($route),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Route $route, RouteService $routeService)
    {

        $this->authorize('delete', $route);

        $routeService->deleteRoute($route);

        return response()->json([
            'message' => 'Route deleted successfully',
        ]);
    }
}
