<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\AuthController;

Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello DroppieTrack'
    ]);
});

Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post(
    '/logout',
    [AuthController::class, 'logout']
);

Route::middleware('auth:sanctum')->post(
    '/logout-all',
    [AuthController::class, 'logoutAll']
);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('routes', RouteController::class);
});

