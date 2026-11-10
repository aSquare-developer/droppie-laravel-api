<?php

use App\Models\Route;
use App\Models\TripReport;
use App\Models\User;
use App\Services\RouteReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function reportDriver(float $odometer): User
{
    return User::factory()
        ->withProfile(['first_name' => 'Mari', 'last_name' => 'Tamm'])
        ->withVehicle(['registration_number' => '123 ABC', 'odometer_km' => $odometer])
        ->create();
}

function reportTrip(User $user, string $date, float $km): Route
{
    return Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => $date,
        'distance_km' => $km,
        'distance_status' => 'completed',
    ]);
}

function startAndEnd(TripReport $report): array
{
    return [$report->odometer_start_km, $report->odometer_end_km];
}

function mileage(User $user): float
{
    return $user->activeVehicle()->value('odometer_km');
}

it('starts each report from the profile mileage and moves it to the end reading', function () {
    $user = reportDriver(1000);
    reportTrip($user, '2026-10-10', 100);
    reportTrip($user, '2026-11-05', 30);
    $service = app(RouteReportService::class);

    expect(startAndEnd($service->getOrCreateReport($user, '2026-10-01', '2026-10-31')))->toBe([1000.0, 1100.0])
        ->and(mileage($user))->toBe(1100.0)
        ->and(startAndEnd($service->getOrCreateReport($user, '2026-11-01', '2026-11-30')))->toBe([1100.0, 1130.0])
        ->and(mileage($user))->toBe(1130.0);
});

it('uses a mileage entered by hand as the start of the next report', function () {
    $user = reportDriver(1000);
    reportTrip($user, '2026-10-10', 100);
    $service = app(RouteReportService::class);
    $service->getOrCreateReport($user, '2026-10-01', '2026-10-31');

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Mari', 'car_mileage' => 2000]);
    reportTrip($user, '2026-11-05', 30);

    expect(startAndEnd($service->getOrCreateReport($user, '2026-11-01', '2026-11-30')))->toBe([2000.0, 2030.0]);
});

it('rejects overlapping and earlier periods so trips are not counted twice', function (string $from, string $to) {
    $user = reportDriver(1000);
    reportTrip($user, '2026-10-10', 100);
    app(RouteReportService::class)->getOrCreateReport($user, '2026-10-01', '2026-10-31');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/reports/routes/pdf?from={$from}&to={$to}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from' => 'Choose a period starting after 31.10.2026.']);

    expect(mileage($user))->toBe(1100.0)
        ->and(TripReport::query()->count())->toBe(1);
})->with([
    'overlapping' => ['2026-10-15', '2026-11-15'],
    'inside' => ['2026-10-01', '2026-10-15'],
    'earlier' => ['2026-09-01', '2026-09-30'],
]);

it('downloads the stored report again for the same period', function () {
    $user = reportDriver(1000);
    reportTrip($user, '2026-10-10', 100);
    $service = app(RouteReportService::class);
    $first = $service->getOrCreateReport($user, '2026-10-01', '2026-10-31');

    expect($service->getOrCreateReport($user, '2026-10-01', '2026-10-31')->id)->toBe($first->id)
        ->and(mileage($user))->toBe(1100.0);
});

it('deletes the latest report and gives its start reading back to the profile', function () {
    $user = reportDriver(1000);
    $route = reportTrip($user, '2026-10-10', 100);
    $service = app(RouteReportService::class);
    $report = $service->getOrCreateReport($user, '2026-10-01', '2026-10-31');
    $route->update(['distance_km' => 120]);

    $this->actingAs($user)
        ->delete(route('web.reports.destroy', $report))
        ->assertRedirect(route('reports'))
        ->assertSessionHas('success');

    $this->assertModelMissing($report);
    expect(mileage($user))->toBe(1000.0)
        ->and(startAndEnd($service->getOrCreateReport($user, '2026-10-01', '2026-10-31')))->toBe([1000.0, 1120.0]);
});

it('keeps a mileage changed by hand when the latest report is deleted', function () {
    $user = reportDriver(1000);
    reportTrip($user, '2026-10-10', 100);
    $service = app(RouteReportService::class);
    $report = $service->getOrCreateReport($user, '2026-10-01', '2026-10-31');
    $user->activeVehicle()->update(['odometer_km' => 5000]);

    $service->deleteReport($report);

    expect(mileage($user))->toBe(5000.0);
});

it('only allows deleting the latest report', function () {
    $user = reportDriver(1000);
    reportTrip($user, '2026-10-10', 100);
    $service = app(RouteReportService::class);
    $older = $service->getOrCreateReport($user, '2026-10-01', '2026-10-31');
    $service->getOrCreateReport($user, '2026-11-01', '2026-11-30');

    expect(fn () => $service->deleteReport($older))->toThrow(ValidationException::class, 'Only the latest report can be deleted.');
    $this->assertModelExists($older);
});

it('returns 404 when deleting another user\'s report', function () {
    $report = app(RouteReportService::class)->getOrCreateReport(reportDriver(1000), '2026-10-01', '2026-10-31');

    $this->actingAs(reportDriver(2000))
        ->delete(route('web.reports.destroy', $report))
        ->assertNotFound();

    $this->assertModelExists($report);
});

it('offers delete only for the latest report and suggests the next period', function () {
    $this->travelTo('2026-11-20 12:00');
    $user = reportDriver(1000);
    $service = app(RouteReportService::class);
    $older = $service->getOrCreateReport($user, '2026-09-01', '2026-09-30');
    $latest = $service->getOrCreateReport($user, '2026-10-01', '2026-10-31');

    $this->actingAs($user)
        ->get(route('reports'))
        ->assertOk()
        ->assertSee('action="'.route('web.reports.destroy', $latest).'"', false)
        ->assertDontSee('action="'.route('web.reports.destroy', $older).'"', false)
        ->assertSee('name="from" type="date" value="2026-11-01"', false)
        ->assertSee('name="to" type="date" value="2026-11-20"', false)
        ->assertSee('Continues after the latest report (01.10.2026 – 31.10.2026).');
});
