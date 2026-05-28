<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\RouteReportController;

Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello DroppieTrack'
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('routes', RouteController::class);
    Route::get('/reports/routes/pdf', [RouteReportController::class, 'download']);
});

