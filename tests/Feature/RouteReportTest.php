<?php

use App\Models\Address;
use App\Models\Route;
use App\Models\User;
use App\Services\RouteReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('does not store generated trip log fields on routes', function () {
    expect(Schema::hasColumn('routes', 'purpose'))->toBeFalse()
        ->and(Schema::hasColumn('routes', 'odometer_start_km'))->toBeFalse()
        ->and(Schema::hasColumn('routes', 'odometer_end_km'))->toBeFalse();
});

it('calculates sequential odometer readings from the profile mileage', function () {
    $user = User::factory()->create([
        'name' => 'Mari',
        'last_name' => 'Tamm',
        'car_registration_number' => '123 ABC',
        'car_mileage' => 1000.2,
    ]);
    $startAddress = Address::factory()->create([
        'formatted_address' => 'Mannerheimintie 1, Helsinki',
    ]);
    $endAddress = Address::factory()->create([
        'formatted_address' => 'Otakaari 1, Espoo',
    ]);

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
        'start_address_id' => $endAddress->id,
        'end_address_id' => $startAddress->id,
        'started_at' => '2026-06-02',
        'distance_km' => 3.7,
        'distance_status' => 'completed',
    ]);

    $service = app(RouteReportService::class);
    $routes = $service->getRoutesForPeriod($user, '2026-06-01', '2026-06-30');
    $tripLogRows = $service->buildTripLogRows($routes, $user->car_mileage);
    $totalDistanceKm = $service->getTotalDistance($routes);

    expect($totalDistanceKm)->toBeFloat()->toBe(6.2)
        ->and($tripLogRows->pluck('odometer_start_km')->all())->toBe([1000.2, 1002.7])
        ->and($tripLogRows->pluck('odometer_end_km')->all())->toBe([1002.7, 1006.4])
        ->and($service->getEndingOdometer($tripLogRows, $user->car_mileage))->toBe(1006.4);

    $this
        ->view('pdf.routes-report', [
            'user' => $user,
            'tripLogRows' => $tripLogRows,
            'from' => '01.06.2026',
            'to' => '30.06.2026',
            'totalDistanceKm' => $totalDistanceKm,
        ])
        ->assertSee('Mari Tamm')
        ->assertSee('123 ABC')
        ->assertSee('Mannerheimintie 1, Helsinki')
        ->assertSee('Otakaari 1, Espoo')
        ->assertSee('1 000.2')
        ->assertSee('1 006.4')
        ->assertSee('6.2 km');
});

it('prevents downloading a trip log when required profile data is missing', function () {
    $user = User::factory()->create([
        'last_name' => null,
        'car_registration_number' => null,
        'car_mileage' => null,
    ]);

    $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/reports/routes/pdf?from=2026-06-01&to=2026-06-30')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('profile');
});

it('downloads a trip log and updates the profile mileage to its ending reading', function () {
    $user = User::factory()->create([
        'name' => 'Mari',
        'last_name' => 'Tamm',
        'car_registration_number' => '123 ABC',
        'car_mileage' => 1500.5,
    ]);

    Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => '2026-06-01',
        'distance_km' => 12.3,
        'distance_status' => 'completed',
    ]);

    $response = $this
        ->actingAs($user, 'sanctum')
        ->post('/api/reports/routes/pdf?from=2026-06-01&to=2026-06-30')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF')
        ->and($user->fresh()->car_mileage)->toBe(1512.8);
});

it('keeps profile mileage unchanged for an empty trip log', function () {
    $user = User::factory()->create([
        'last_name' => 'Tamm',
        'car_registration_number' => '123 ABC',
        'car_mileage' => 1500.5,
    ]);

    $this
        ->actingAs($user, 'sanctum')
        ->post('/api/reports/routes/pdf?from=2026-06-01&to=2026-06-30')
        ->assertOk();

    expect($user->fresh()->car_mileage)->toBe(1500.5);
});
