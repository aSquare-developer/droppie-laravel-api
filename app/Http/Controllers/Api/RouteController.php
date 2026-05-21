<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Route;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreRouteRequest;

class RouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $routes = Route::latest()->get();
        
        return response()->json($routes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRouteRequest $request)
    {
        $route = Route::create($request->validated());

        return response()->json([
            'message' => 'Route created successfully',
            'data' => $route
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
