<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Models\Route;

use App\Http\Requests\StoreRouteRequest;
use App\Http\Requests\UpdateRouteRequest;

use App\Http\Resources\RouteResource;


class RouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $query = $request->user()->routes();

        if($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('start_address', 'like', '%' . $request->search . '%')
                  ->orWhere('end_address', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('min_distance')) {
            $query->where('distance_km', '>=', $request->min_distance);
        }

        if ($request->filled('max_distance')) {
            $query->where('distance_km', '<=', $request->max_distance);
        }

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
        
        return RouteResource::collection($routes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRouteRequest $request)
    {
        $route = $request->user()->routes()->create(
            $request->validated()
        );

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
        return response()->json([
            'data' => new RouteResource($route)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRouteRequest $request, Route $route)
    {
        $route->update($request->validated());

        return response()->json([
            'message' => 'Route updated successfully',
            'data' => new RouteResource($route)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Route $route)
    {
        $route->delete();

        return response()->json([
            'message' => 'Route deleted successfully'
        ]);
    }
}
