<?php

use App\Models\Route;
use App\Models\TripReport;
use App\Models\User;
use App\Services\RouteReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportReadyUser(float $odometer = 1000.0): User
{
    return User::factory()
        ->withProfile(['first_name' => 'Mari', 'last_name' => 'Tamm'])
        ->withVehicle(['registration_number' => '123 ABC', 'odometer_km' => $odometer, 'odometer_recorded_on' => '2026-05-31'])
        ->create();
}

it('redirects guests away from the account and reports pages', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['dashboard', 'account', 'reports']);

it('links the three pages from the top bar and marks the current one', function () {
    $user = reportReadyUser();

    $this->actingAs($user)
        ->get(route('reports'))
        ->assertOk()
        ->assertSee(route('dashboard'))
        ->assertSee(route('account'))
        ->assertSeeInOrder(['href="'.route('reports').'"', 'aria-current="page"', '>Reports</a>'], false);
});

it('shows the profile form on the account page', function () {
    $user = reportReadyUser();

    $this->actingAs($user)
        ->get(route('account'))
        ->assertOk()
        ->assertSee('Save profile')
        ->assertSee('value="Tamm"', false)
        ->assertSee('value="123 ABC"', false)
        ->assertDontSee('Download PDF')
        ->assertDontSee('New route');
});

it('returns to the account page with errors when the profile is invalid', function () {
    $user = reportReadyUser();

    $this->actingAs($user)
        ->from(route('account'))
        ->patch(route('profile.update'), ['name' => ''])
        ->assertRedirect(route('account'))
        ->assertSessionHasErrors('name');
});

it('asks for an active vehicle on the dashboard when there is none', function () {
    $user = reportReadyUser();
    $user->vehicles()->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Add an active vehicle to your profile before adding trips.')
        ->assertSee(route('account'));
});

it('shows the report form and an empty history on the reports page', function () {
    $this->actingAs(reportReadyUser())
        ->get(route('reports'))
        ->assertOk()
        ->assertSee('New PDF report')
        ->assertSee('Download PDF')
        ->assertSee('No reports yet')
        ->assertDontSee('Save profile');
});

it('lists only the user\'s own reports, newest first', function () {
    $user = reportReadyUser();
    $service = app(RouteReportService::class);

    $this->travelTo('2026-07-01 10:00');
    $service->getOrCreateReport($user, '2026-05-01', '2026-05-31');
    $this->travelTo('2026-08-01 10:00');
    $service->getOrCreateReport($user, '2026-06-01', '2026-06-30');

    $stranger = reportReadyUser();
    $service->getOrCreateReport($stranger, '2026-01-01', '2026-01-31');

    $this->actingAs($user)
        ->get(route('reports'))
        ->assertOk()
        ->assertSee('2 reports')
        ->assertSeeInOrder(['01.06.2026 – 30.06.2026', '01.05.2026 – 31.05.2026'])
        ->assertSee('123 ABC')
        ->assertDontSee('01.01.2026');
});

it('links profile problems in report validation to the account page', function () {
    $user = User::factory()->withProfile(['first_name' => 'Mari', 'last_name' => null])->create();

    $this->actingAs($user)
        ->from(route('reports'))
        ->followingRedirects()
        ->post(route('web.reports.routes.pdf'), ['from' => '2026-06-01', 'to' => '2026-06-30'])
        ->assertOk()
        ->assertSee('New PDF report')
        ->assertSee('The report needs a complete profile.')
        ->assertSee('Add the driver last name to the profile.')
        ->assertSee(route('account'));
});

it('downloads a stored report again without creating a new one or changing the odometer', function () {
    $user = reportReadyUser(1000.0);
    Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => '2026-06-01',
        'distance_km' => 12.3,
        'distance_status' => 'completed',
    ]);

    $report = app(RouteReportService::class)->getOrCreateReport($user, '2026-06-01', '2026-06-30');
    expect([$report->odometer_start_km, $report->odometer_end_km])->toBe([1000.0, 1012.3]);

    $response = $this->actingAs($user)
        ->get(route('web.reports.pdf', $report))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="routes-report-01.06.2026-30.06.2026.pdf"');

    expect($response->getContent())->toStartWith('%PDF')
        ->and(TripReport::query()->count())->toBe(1)
        ->and($user->activeVehicle()->value('odometer_km'))->toBe(1000.0);
});

it('returns 404 for another user\'s report', function () {
    $owner = reportReadyUser();
    $report = app(RouteReportService::class)->getOrCreateReport($owner, '2026-06-01', '2026-06-30');

    $this->actingAs(reportReadyUser())
        ->get(route('web.reports.pdf', $report))
        ->assertNotFound();
});
