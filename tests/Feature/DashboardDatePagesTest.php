<?php

use App\Models\Address;
use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dayTrip(User $user, string $date, string $from, float $km = 10, ?string $createdAt = null): Route
{
    return Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => $date,
        'distance_km' => $km,
        'distance_status' => 'completed',
        'start_address_id' => Address::factory()->create(['formatted_address' => $from, 'city' => 'Tartu'])->id,
        'created_at' => $createdAt ?? now(),
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    dayTrip($this->user, '2026-02-06', 'Riia tn 1, Tartu', 5);
    dayTrip($this->user, '2026-02-08', 'Raatuse tn 18, Tartu', 3.3, '2026-02-08 10:00');
    dayTrip($this->user, '2026-02-08', 'Aleksandri tn 5, Tartu', 0.5, '2026-02-08 09:00');
    dayTrip($this->user, '2026-02-07', 'Vanemuise tn 1, Tartu', 12);
});

it('opens on the newest trip date and lists its routes newest first', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Sunday, 08 Feb 2026')
        ->assertSee('2 routes')
        ->assertSee('3.8 km')
        ->assertSee('day 1 of 3')
        ->assertSeeInOrder(['Raatuse tn 18, Tartu', 'Aleksandri tn 5, Tartu'])
        ->assertDontSee('Riia tn 1, Tartu')
        ->assertDontSee('Vanemuise tn 1, Tartu');
});

it('links to the neighbouring dates', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard', ['date' => '2026-02-07']))
        ->assertOk()
        ->assertSee('Saturday, 07 Feb 2026')
        ->assertSee('day 2 of 3')
        ->assertSee(route('dashboard', ['date' => '2026-02-08']), false)
        ->assertSee(route('dashboard', ['date' => '2026-02-06']), false)
        ->assertSee('Vanemuise tn 1, Tartu')
        ->assertDontSee('Raatuse tn 18, Tartu');
});

it('keeps the overall metrics independent of the selected date', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard', ['date' => '2026-02-06']))
        ->assertOk()
        ->assertSeeInOrder(['Routes', '4', 'Kilometers', '20.8'], false);
});

it('shows an empty day with links to the dates around it', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard', ['date' => '2026-02-10']))
        ->assertOk()
        ->assertSee('No trips on this date')
        ->assertSee(route('dashboard', ['date' => '2026-02-08']), false);
});

it('pages through search results by date', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard', ['search' => 'riia']))
        ->assertOk()
        ->assertSee('Friday, 06 Feb 2026')
        ->assertSee('day 1 of 1')
        ->assertSee('Riia tn 1, Tartu');
});

it('ignores an invalid date parameter', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard', ['date' => '2026-02-31']))
        ->assertOk()
        ->assertSee('Sunday, 08 Feb 2026');
});

it('opens the date of the route being edited', function () {
    $route = Route::query()->whereDate('started_at', '2026-02-06')->firstOrFail();

    $this->actingAs($this->user)
        ->get(route('dashboard', ['edit' => $route->id]))
        ->assertOk()
        ->assertSee('Edit route')
        ->assertSee('Friday, 06 Feb 2026');
});

it('returns 404 when editing another user\'s route', function () {
    $route = Route::query()->firstOrFail();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', ['edit' => $route->id]))
        ->assertNotFound();
});

it('shows the empty state for a user without routes', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No routes yet');
});

it('marks the saved addresses of an edited route as valid instead of showing postal hints', function () {
    $route = Route::query()->whereDate('started_at', '2026-02-06')->firstOrFail();
    $route->startAddress->update(['postal_code' => '51004', 'city' => 'Tartu']);

    $this->actingAs($this->user)
        ->get(route('dashboard', ['edit' => $route->id]))
        ->assertOk()
        ->assertSee('data-address-input="start"', false)
        ->assertSee('data-address-state="valid"', false)
        ->assertSee('<small class="address-hint" data-address-hint="start"></small>', false)
        ->assertDontSee('51004, Tartu');
});
