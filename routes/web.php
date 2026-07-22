<?php

use App\Http\Controllers\Web\AddressController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GoogleAuthController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\RouteController;
use App\Http\Controllers\Web\RouteReportController;
use App\Http\Controllers\Web\RouteStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register.store');

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('oauth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:30,1')
        ->name('oauth.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/addresses/autocomplete', [AddressController::class, 'autocomplete'])
        ->middleware('throttle:address-autocomplete')
        ->name('web.addresses.autocomplete');
    Route::post('/addresses/validate', [AddressController::class, 'validateAddress'])
        ->middleware('throttle:address-validation')
        ->name('web.addresses.validate');

    Route::get('/routes/status', [RouteStatusController::class, 'index'])->name('web.routes.status');
    Route::post('/routes', [RouteController::class, 'store'])
        ->middleware('throttle:route-write')
        ->name('web.routes.store');
    Route::put('/routes/{route}', [RouteController::class, 'update'])
        ->middleware('throttle:route-write')
        ->name('web.routes.update');
    Route::delete('/routes/{route}', [RouteController::class, 'destroy'])->name('web.routes.destroy');

    Route::post('/reports/routes/pdf', [RouteReportController::class, 'download'])
        ->middleware('throttle:reports')
        ->name('web.reports.routes.pdf');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
