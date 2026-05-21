<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RouteController;

Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello DroppieTrack'
    ]);
});

Route::apiResource('routes', RouteController::class);