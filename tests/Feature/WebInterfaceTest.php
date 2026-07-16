<?php

use App\Jobs\CalculateRouteDistance;
use App\Models\Route;
use App\Models\User;
use App\Services\GoogleAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('shows blade auth screens and registers a web session user', function () {
    $this->get('/')
        ->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in')
        ->assertSee('Droppie');

    $this
        ->post(route('register.store'), [
            'name' => 'Mari',
            'email' => 'mari@example.com',
            'password' => 'password',
        ])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'mari@example.com']);
    $this->assertDatabaseHas('user_profiles', ['first_name' => 'Mari']);
    $this->assertDatabaseHas('vehicles', ['is_active' => true]);
});

it('renders the dashboard with blade route and profile sections', function () {
    $user = User::factory()
        ->withProfile(['first_name' => 'Mari', 'last_name' => 'Tamm'])
        ->withVehicle(['registration_number' => '123 ABC', 'odometer_km' => 1000.2])
        ->create();

    Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => '2026-06-01',
        'distance_km' => 12.3,
        'distance_status' => 'completed',
    ]);

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('New route')
        ->assertSee('Profile')
        ->assertSee('PDF report')
        ->assertSee('Routes')
        ->assertSee('12.3 km')
        ->assertSee('Mari');
});

it('updates profile fields through the web form and keeps email immutable', function () {
    $user = User::factory()->create([
        'email' => 'driver@example.com',
    ]);

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Olga',
            'last_name' => 'Sokolova',
            'company_name' => 'Fast Drops',
            'car_registration_number' => 'XYZ-789',
            'car_make_model' => 'Ford Transit',
            'car_mileage' => 92000.5,
            'country' => 'Estonia',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'driver@example.com',
    ]);
    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'first_name' => 'Olga',
        'last_name' => 'Sokolova',
        'company_name' => 'Fast Drops',
        'country' => 'Estonia',
    ]);
    $this->assertDatabaseHas('vehicles', [
        'user_id' => $user->id,
        'registration_number' => 'XYZ-789',
        'make_model' => 'Ford Transit',
        'odometer_km' => 92000.5,
        'is_active' => true,
    ]);

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'email' => 'new@example.com',
        ])
        ->assertSessionHasErrors('email');
});

it('creates a route from the blade form using selected google place ids', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock
            ->shouldReceive('validatePlace')
            ->once()
            ->with('start-place', null)
            ->andReturn(webAddressPayload('start-place', 'Mannerheimintie 1, Helsinki'));

        $mock
            ->shouldReceive('validatePlace')
            ->once()
            ->with('end-place', null)
            ->andReturn(webAddressPayload('end-place', 'Otakaari 1, Espoo'));
    });

    $this
        ->actingAs($user)
        ->post(route('web.routes.store'), [
            'start_address_display' => 'Mannerheimintie 1, Helsinki',
            'start_place_id' => 'start-place',
            'end_address_display' => 'Otakaari 1, Espoo',
            'end_place_id' => 'end-place',
            'started_at' => '2026-06-01',
        ])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('addresses', [
        'place_id' => 'start-place',
        'formatted_address' => 'Mannerheimintie 1, Helsinki',
    ]);
    $this->assertDatabaseHas('routes', [
        'user_id' => $user->id,
        'started_at' => '2026-06-01 00:00:00',
        'distance_status' => 'pending',
    ]);
    Queue::assertPushed(CalculateRouteDistance::class);
});

it('returns web route statuses for polling only for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $route = Route::factory()->create([
        'user_id' => $user->id,
        'distance_status' => 'processing',
    ]);
    $otherRoute = Route::factory()->create([
        'user_id' => $otherUser->id,
        'distance_status' => 'processing',
    ]);

    $this
        ->actingAs($user)
        ->getJson(route('web.routes.status', ['ids' => $route->id.','.$otherRoute->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $route->id)
        ->assertJsonPath('data.0.distance_status', 'processing');
});

function webAddressPayload(string $placeId, string $formattedAddress): array
{
    return [
        'place_id' => $placeId,
        'formatted_address' => $formattedAddress,
        'postal_code' => '00100',
        'city' => str_contains($formattedAddress, 'Espoo') ? 'Espoo' : 'Helsinki',
        'country' => 'Finland',
        'country_code' => 'FI',
        'street' => str_contains($formattedAddress, 'Espoo') ? 'Otakaari' : 'Mannerheimintie',
        'street_number' => '1',
        'latitude' => 60.17,
        'longitude' => 24.94,
    ];
}
