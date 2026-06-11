<?php

use App\Models\Address;
use App\Models\Route;
use App\Models\User;
use App\Services\RouteReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preserves fractional kilometers in route report totals', function () {
    $user = User::factory()->create();
    $startAddress = Address::factory()->create();
    $endAddress = Address::factory()->create();

    Route::factory()->create([
        'user_id' => $user->id,
        'start_address_id' => $startAddress->id,
        'end_address_id' => $endAddress->id,
        'started_at' => '2026-06-01',
        'distance_km' => 2.5,
        'distance_status' => 'completed',
    ]);

    Route::factory()->create([
        'user_id' => $user->id,
        'start_address_id' => $startAddress->id,
        'end_address_id' => $endAddress->id,
        'started_at' => '2026-06-02',
        'distance_km' => 3.7,
        'distance_status' => 'completed',
    ]);

    $service = app(RouteReportService::class);
    $routes = $service->getRoutesForPeriod($user, '2026-06-01', '2026-06-30');
    $totalDistanceKm = $service->getTotalDistance($routes);

    expect($totalDistanceKm)->toBeFloat()->toBe(6.2);

    $this
        ->view('pdf.routes-report', [
            'user' => $user,
            'routes' => $routes,
            'from' => '01.06.2026',
            'to' => '30.06.2026',
            'totalDistanceKm' => $totalDistanceKm,
        ])
        ->assertSee('2.5 km')
        ->assertSee('3.7 km')
        ->assertSee('6.2 km');
});
