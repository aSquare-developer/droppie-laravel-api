<?php

use App\Models\Route;
use App\Models\User;
use App\Services\GoogleAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('rejects a new route without an active vehicle before looking up addresses', function () {
    $user = User::factory()->create();
    $user->vehicles()->delete();

    $google = Mockery::mock(GoogleAddressService::class);
    $google->shouldNotReceive('validatePlace');
    app()->instance(GoogleAddressService::class, $google);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/routes', [
            'start_place_id' => 'start-place',
            'end_place_id' => 'end-place',
            'started_at' => '2026-06-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'vehicle' => 'Add an active vehicle to the profile before creating a route.',
        ]);

    expect(Route::query()->count())->toBe(0);
});

it('assigns the active vehicle when a route is created without one', function () {
    $user = User::factory()->create();

    $route = Route::factory()->create(['user_id' => $user->id]);

    expect($route->vehicle_id)->toBe($user->activeVehicle()->value('id'));
});

it('refuses to create a route directly for a user without an active vehicle', function () {
    $user = User::factory()->create();
    $user->vehicles()->update(['is_active' => false]);

    Route::factory()->create(['user_id' => $user->id]);
})->throws(ValidationException::class, 'Add an active vehicle to the profile before creating a route.');
