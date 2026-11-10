<?php

use App\Models\Route;
use App\Models\TripReport;
use App\Models\User;
use App\Services\RouteReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function driver(float $odometer, string $recordedOn): User
{
    return User::factory()
        ->withProfile(['first_name' => 'Mari', 'last_name' => 'Tamm'])
        ->withVehicle(['registration_number' => '123 ABC', 'odometer_km' => $odometer, 'odometer_recorded_on' => $recordedOn])
        ->create();
}

function trip(User $user, string $date, float $km, string $status = 'completed'): Route
{
    return Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => $date,
        'distance_km' => $km,
        'distance_status' => $status,
    ]);
}

function readings(TripReport $report): array
{
    return [$report->odometer_start_km, $report->odometer_end_km];
}

it('gives consistent readings for overlapping reports generated in any order', function () {
    $user = driver(1000, '2026-09-30');
    trip($user, '2026-10-10', 100);
    trip($user, '2026-10-20', 50);
    $service = app(RouteReportService::class);

    $secondHalf = $service->getOrCreateReport($user, '2026-10-16', '2026-10-31');
    $month = $service->getOrCreateReport($user, '2026-10-01', '2026-10-31');
    $firstHalf = $service->getOrCreateReport($user, '2026-10-01', '2026-10-15');

    expect(readings($month))->toBe([1000.0, 1150.0])
        ->and(readings($firstHalf))->toBe([1000.0, 1100.0])
        ->and(readings($secondHalf))->toBe([1100.0, 1150.0])
        ->and($user->activeVehicle()->value('odometer_km'))->toBe(1000.0);
});

it('counts backwards for periods before the baseline date', function () {
    $user = driver(1000, '2026-10-31');
    trip($user, '2026-10-10', 100);
    trip($user, '2026-10-20', 50);

    $report = app(RouteReportService::class)->getOrCreateReport($user, '2026-10-01', '2026-10-15');

    expect(readings($report))->toBe([850.0, 950.0])
        ->and(collect($report->rows)->pluck('odometer_end_km')->all())->toEqual([950.0]);
});

it('treats the baseline as the reading at the end of its day', function () {
    $user = driver(1000, '2026-10-10');
    trip($user, '2026-10-10', 100);
    trip($user, '2026-10-20', 50);
    $service = app(RouteReportService::class);

    expect(readings($service->getOrCreateReport($user, '2026-10-10', '2026-10-10')))->toBe([900.0, 1000.0])
        ->and(readings($service->getOrCreateReport($user, '2026-10-11', '2026-10-31')))->toBe([1000.0, 1050.0]);
});

it('counts only completed trips of the vehicle', function () {
    $user = driver(1000, '2026-09-30');
    trip($user, '2026-10-10', 100);
    trip($user, '2026-10-11', 40, 'failed');
    trip($user, '2026-10-12', 30, 'processing');
    $other = driver(5000, '2026-09-30');
    trip($other, '2026-10-10', 999);

    $vehicle = $user->activeVehicle()->first();

    expect($vehicle->odometerAt('2026-10-31'))->toBe(1100.0)
        ->and($vehicle->odometerAt('2026-09-01'))->toBe(1000.0);
});

it('moves the baseline date only when the mileage changes', function () {
    $user = driver(1000, '2026-09-30');
    $this->travelTo('2026-11-05 18:00');

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Mari', 'car_mileage' => 1000]);
    expect($user->activeVehicle()->first()->odometer_recorded_on->toDateString())->toBe('2026-09-30');

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Mari', 'car_mileage' => 1234.5]);
    $vehicle = $user->activeVehicle()->first();
    expect($vehicle->odometer_km)->toBe(1234.5)
        ->and($vehicle->odometer_recorded_on->toDateString())->toBe('2026-11-05');
});

it('shows the baseline date and the current mileage on the account page and in the api', function () {
    $user = driver(1000, '2026-09-30');
    trip($user, '2026-10-10', 100.5);
    $this->travelTo('2026-11-05 12:00');

    $this->actingAs($user)->get(route('account'))
        ->assertOk()
        ->assertSee('Reading on 30.09.2026.')
        ->assertSee('1 100.5 km');

    $this->actingAs($user, 'sanctum')->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.car_mileage', 1000)
        ->assertJsonPath('user.car_mileage_recorded_on', '2026-09-30')
        ->assertJsonPath('user.car_current_mileage', 1100.5);
});

it('deletes a report so the period can be generated again with current data', function () {
    $user = driver(1000, '2026-09-30');
    $route = trip($user, '2026-10-10', 100);
    $service = app(RouteReportService::class);
    $report = $service->getOrCreateReport($user, '2026-10-01', '2026-10-31');

    $route->update(['distance_km' => 120]);

    $this->actingAs($user)
        ->delete(route('web.reports.destroy', $report))
        ->assertRedirect(route('reports'))
        ->assertSessionHas('success');

    $this->assertModelMissing($report);
    expect(readings($service->getOrCreateReport($user, '2026-10-01', '2026-10-31')))->toBe([1000.0, 1120.0]);
});

it('returns 404 when deleting another user\'s report', function () {
    $report = app(RouteReportService::class)->getOrCreateReport(driver(1000, '2026-09-30'), '2026-10-01', '2026-10-31');

    $this->actingAs(driver(2000, '2026-09-30'))
        ->delete(route('web.reports.destroy', $report))
        ->assertNotFound();

    $this->assertModelExists($report);
});
