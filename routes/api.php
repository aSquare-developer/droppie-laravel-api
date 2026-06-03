<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\RouteReportController;
use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello Droppie',
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/addresses/autocomplete', [AddressController::class, 'autocomplete']);
    Route::post('/addresses/validate', [AddressController::class, 'validateAddress']);
    Route::apiResource('routes', RouteController::class);
    Route::get('/reports/routes/pdf', [RouteReportController::class, 'download']);
});
