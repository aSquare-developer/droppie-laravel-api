<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\AuthController;

Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello DroppieTrack'
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::apiResource('routes', RouteController::class);

