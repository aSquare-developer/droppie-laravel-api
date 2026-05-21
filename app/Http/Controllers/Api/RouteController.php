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
    public function index()
    {
        $routes = Route::latest()->paginate(10);
        
        return RouteResource::collection($routes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRouteRequest $request)
    {
        $route = Route::create($request->validated());

        return response()->json([
            'message' => 'Route created successfully',
            'data' => new RouteResource($route)
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
