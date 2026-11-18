<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.google.places_api_key', 'places-key');
    Config::set('services.google.places_country', 'FI,EE');
    Http::preventStrayRequests();
});

function fakeFindPlace(float $lat = 58.378, float $lng = 26.729): void
{
    Http::fake([
        'maps.googleapis.com/maps/api/place/findplacefromtext/json*' => Http::response([
            'status' => 'OK',
            'candidates' => [['geometry' => ['location' => ['lat' => $lat, 'lng' => $lng]]]],
        ]),
    ]);
}

it('shows country and city fields on the account page', function () {
    $user = User::factory()->withProfile(['first_name' => 'Artur', 'country_code' => 'EE', 'country' => 'Estonia', 'city' => 'Tartu'])->create();

    $this->actingAs($user)
        ->get(route('account'))
        ->assertOk()
        ->assertSee('<option value="EE" selected>Estonia</option>', false)
        ->assertSee('name="city"', false)
        ->assertSee('value="Tartu"', false)
        ->assertDontSee('Country of residence');
});

it('saves the country code with its name and locates the city', function () {
    fakeFindPlace();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Artur', 'country_code' => 'ee', 'city' => 'Tartu'])
        ->assertRedirect(route('account'));

    $profile = $user->profile()->first();

    expect($profile->country_code)->toBe('EE')
        ->and($profile->country)->toBe('Estonia')
        ->and($profile->city)->toBe('Tartu')
        ->and($profile->city_latitude)->toBe(58.378)
        ->and($profile->city_longitude)->toBe(26.729);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'findplacefromtext')
        && $request['input'] === 'Tartu, Estonia'
        && $request['fields'] === 'geometry');
});

it('does not look the city up again when it did not change', function () {
    fakeFindPlace();
    $user = User::factory()->withProfile([
        'first_name' => 'Artur', 'country_code' => 'EE', 'country' => 'Estonia', 'city' => 'Tartu',
        'city_latitude' => 58.378, 'city_longitude' => 26.729,
    ])->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Artur', 'country_code' => 'EE', 'city' => 'Tartu', 'company_name' => 'Droppie']);

    Http::assertNothingSent();
    expect($user->profile()->first()->city_latitude)->toBe(58.378);
});

it('saves the profile without coordinates when the city cannot be located', function (int $status, array $body) {
    Http::fake(['*' => Http::response($body, $status)]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Artur', 'country_code' => 'EE', 'city' => 'Nowhere'])
        ->assertRedirect(route('account'))
        ->assertSessionHasNoErrors();

    $profile = $user->profile()->first();
    expect($profile->city)->toBe('Nowhere')
        ->and($profile->city_latitude)->toBeNull();
})->with([
    'no results' => [200, ['status' => 'ZERO_RESULTS', 'candidates' => []]],
    'google error' => [500, []],
]);

it('clears the coordinates when the city is removed', function () {
    $user = User::factory()->withProfile([
        'first_name' => 'Artur', 'country_code' => 'EE', 'city' => 'Tartu', 'city_latitude' => 58.378, 'city_longitude' => 26.729,
    ])->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Artur', 'country_code' => 'EE', 'city' => '']);

    expect($user->profile()->first())->city->toBeNull()->city_latitude->toBeNull();
    Http::assertNothingSent();
});

it('rejects an unknown country code', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->patchJson('/api/profile', ['country_code' => 'XX'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('country_code');
});

it('maps a country name sent by older api clients to its code', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/profile', ['country' => 'estonia'])
        ->assertOk()
        ->assertJsonPath('user.country_code', 'EE');
});

it('prefers the profile country and city in address suggestions', function () {
    Http::fake(['maps.googleapis.com/maps/api/place/autocomplete/json*' => Http::response(['status' => 'ZERO_RESULTS'])]);
    $user = User::factory()->withProfile([
        'first_name' => 'Artur', 'country_code' => 'EE', 'city' => 'Tartu', 'city_latitude' => 58.378, 'city_longitude' => 26.729,
    ])->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/addresses/autocomplete?input=Riia')->assertOk();

    Http::assertSent(fn (Request $request): bool => $request['region'] === 'ee'
        && $request['location'] === '58.378,26.729'
        && (int) $request['radius'] === 50000
        && $request['components'] === 'country:fi|country:ee');
});

it('sends no preference when the profile has no country or city', function () {
    Http::fake(['maps.googleapis.com/maps/api/place/autocomplete/json*' => Http::response(['status' => 'ZERO_RESULTS'])]);

    $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/addresses/autocomplete?input=Riia')->assertOk();

    Http::assertSent(fn (Request $request): bool => ! isset($request['region']) && ! isset($request['location']));
});
