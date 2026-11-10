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
        ->and(Schema::hasColumn('routes', 'odometer_end_km'))->toBeFalse()
        ->and(Schema::hasColumn('routes', 'vehicle_id'))->toBeTrue()
        ->and(Schema::hasTable('trip_reports'))->toBeTrue();
});

it('keeps authentication data only in users', function () {
    expect(Schema::hasColumn('users', 'name'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'last_name'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'company_name'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'car_registration_number'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'car_mileage'))->toBeFalse()
        ->and(Schema::hasTable('user_profiles'))->toBeTrue()
        ->and(Schema::hasTable('vehicles'))->toBeTrue();
});

it('calculates sequential odometer readings from the profile mileage', function () {
    $user = User::factory()
        ->withProfile(['first_name' => 'Mari', 'last_name' => 'Tamm'])
        ->withVehicle(['registration_number' => '123 ABC', 'odometer_km' => 1000.2])
        ->create();
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
    $report = $service->getOrCreateReport($user, '2026-06-01', '2026-06-30');

    expect($report->total_distance_km)->toBe(6.2)
        ->and(collect($report->rows)->pluck('odometer_start_km')->all())->toBe([1000.2, 1002.7])
        ->and(collect($report->rows)->pluck('odometer_end_km')->all())->toBe([1002.7, 1006.4]);

    $this
        ->view('pdf.routes-report', [
            'report' => $report,
            'from' => '01.06.2026',
            'to' => '30.06.2026',
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
    $user = User::factory()
        ->withProfile(['last_name' => null])
        ->withVehicle(['registration_number' => null, 'odometer_km' => null])
        ->create();

    $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/reports/routes/pdf?from=2026-06-01&to=2026-06-30')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('profile');
});

it('downloads a trip log and updates the profile mileage to its ending reading', function () {
    $user = User::factory()
        ->withProfile(['first_name' => 'Mari', 'last_name' => 'Tamm'])
        ->withVehicle(['registration_number' => '123 ABC', 'odometer_km' => 1500.5])
        ->create();

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
        ->and($user->activeVehicle()->value('odometer_km'))->toBe(1512.8);
});

it('keeps profile mileage unchanged for an empty trip log', function () {
    $user = User::factory()
        ->withProfile(['last_name' => 'Tamm'])
        ->withVehicle(['registration_number' => '123 ABC', 'odometer_km' => 1500.5])
        ->create();

    $this
        ->actingAs($user, 'sanctum')
        ->post('/api/reports/routes/pdf?from=2026-06-01&to=2026-06-30')
        ->assertOk();

    expect($user->activeVehicle()->value('odometer_km'))->toBe(1500.5);
});

it('reuses an existing report without increasing the odometer twice', function () {
    $user = User::factory()
        ->withProfile(['last_name' => 'Tamm'])
        ->withVehicle(['registration_number' => '123 ABC', 'odometer_km' => 2000])
        ->create();

    Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => '2026-06-01',
        'distance_km' => 10,
        'distance_status' => 'completed',
    ]);

    $this
        ->actingAs($user, 'sanctum')
        ->post('/api/reports/routes/pdf?from=2026-06-01&to=2026-06-30')
        ->assertOk();

    $this
        ->actingAs($user, 'sanctum')
        ->post('/api/reports/routes/pdf?from=2026-06-01&to=2026-06-30')
        ->assertOk();

    expect($user->activeVehicle()->value('odometer_km'))->toBe(2010.0);

    $this->assertDatabaseCount('trip_reports', 1);
});

it('redownloads an immutable report after profile and route data changes', function () {
    $user = User::factory()
        ->withProfile(['first_name' => 'Mari', 'last_name' => 'Tamm'])
        ->withVehicle(['registration_number' => '123 ABC', 'odometer_km' => 3000])
        ->create();

    $route = Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => '2026-06-01',
        'distance_km' => 5,
        'distance_status' => 'completed',
    ]);

    $service = app(RouteReportService::class);
    $firstReport = $service->getOrCreateReport($user, '2026-06-01', '2026-06-30');

    $user->profile()->update(['first_name' => 'Changed', 'last_name' => null]);
    $user->activeVehicle()->update(['registration_number' => null]);
    $route->update(['distance_km' => 50]);

    $sameReport = $service->getOrCreateReport($user->fresh(), '2026-06-01', '2026-06-30');

    expect($sameReport->id)->toBe($firstReport->id)
        ->and($sameReport->profile_snapshot['first_name'])->toBe('Mari')
        ->and($sameReport->vehicle_snapshot['registration_number'])->toBe('123 ABC')
        ->and($sameReport->total_distance_km)->toBe(5.0)
        ->and($sameReport->odometer_end_km)->toBe(3005.0);
});
