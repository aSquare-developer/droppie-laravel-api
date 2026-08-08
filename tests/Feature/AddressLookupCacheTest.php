<?php

use App\Jobs\CalculateRouteDistance;
use App\Models\Address;
use App\Models\Route;
use App\Models\User;
use App\Services\GoogleAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('returns local address autocomplete suggestions before calling google', function () {
    $user = User::factory()->create();

    $address = Address::factory()->create([
        'place_id' => 'known-place',
        'formatted_address' => 'Mannerheimintie 1, 00100 Helsinki, Finland',
        'postal_code' => '00100',
        'city' => 'Helsinki',
        'country' => 'Finland',
        'street' => 'Mannerheimintie',
        'street_number' => '1',
    ]);
    $user->addressUsages()->create([
        'address_id' => $address->id,
        'use_count' => 1,
        'last_used_at' => now(),
    ]);

    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock->shouldReceive('autocomplete')->never();
    });

    $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/addresses/autocomplete?input=Mannerheimintie')
        ->assertOk()
        ->assertJsonPath('data.0.place_id', 'known-place')
        ->assertJsonPath('data.0.description', 'Mannerheimintie 1, 00100 Helsinki, Finland')
        ->assertJsonPath('data.0.main_text', 'Mannerheimintie 1');
});

it('does not expose another users address history in autocomplete', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $address = Address::factory()->create([
        'place_id' => 'private-history-place',
        'formatted_address' => 'Mannerheimintie 1, 00100 Helsinki, Finland',
        'street' => 'Mannerheimintie',
        'street_number' => '1',
    ]);

    $owner->addressUsages()->create([
        'address_id' => $address->id,
        'use_count' => 3,
        'last_used_at' => now(),
    ]);

    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock
            ->shouldReceive('autocomplete')
            ->once()
            ->with('Mannerheimintie', null)
            ->andReturn([]);
    });

    $this
        ->actingAs($owner, 'sanctum')
        ->getJson('/api/addresses/autocomplete?input=Mannerheimintie')
        ->assertOk()
        ->assertJsonPath('data.0.place_id', 'private-history-place');

    $this
        ->actingAs($otherUser, 'sanctum')
        ->getJson('/api/addresses/autocomplete?input=Mannerheimintie')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonMissing(['place_id' => 'private-history-place']);
});

it('validates a known place id from local storage without calling google', function () {
    $user = User::factory()->create();

    $address = Address::factory()->create([
        'place_id' => 'known-place',
        'formatted_address' => 'Mannerheimintie 1, 00100 Helsinki, Finland',
        'postal_code' => '00100',
        'city' => 'Helsinki',
        'country' => 'Finland',
        'country_code' => 'FI',
        'street' => 'Mannerheimintie',
        'street_number' => '1',
        'latitude' => 60.1699,
        'longitude' => 24.9384,
    ]);

    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock->shouldReceive('validatePlace')->never();
    });

    $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/addresses/validate', [
            'place_id' => 'known-place',
        ])
        ->assertOk()
        ->assertJsonPath('data.place_id', 'known-place')
        ->assertJsonPath('data.formatted_address', 'Mannerheimintie 1, 00100 Helsinki, Finland')
        ->assertJsonPath('data.postal_code', '00100')
        ->assertJsonPath('data.city', 'Helsinki');

    $this->assertDatabaseHas('address_usages', [
        'user_id' => $user->id,
        'address_id' => $address->id,
        'use_count' => 1,
    ]);
});

it('creates a route with known place ids without google place details calls', function () {
    Queue::fake();

    $user = User::factory()->create();
    $startAddress = Address::factory()->create([
        'place_id' => 'known-start',
        'formatted_address' => 'Mannerheimintie 1, 00100 Helsinki, Finland',
    ]);
    $endAddress = Address::factory()->create([
        'place_id' => 'known-end',
        'formatted_address' => 'Otakaari 1, 02150 Espoo, Finland',
    ]);

    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock->shouldReceive('validatePlace')->never();
    });

    $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/routes', [
            'start_place_id' => 'known-start',
            'end_place_id' => 'known-end',
            'started_at' => '2026-06-01',
        ])
        ->assertCreated()
        ->assertJsonPath('data.start_place_id', 'known-start')
        ->assertJsonPath('data.end_place_id', 'known-end');

    $this->assertDatabaseHas('routes', [
        'user_id' => $user->id,
        'start_address_id' => $startAddress->id,
        'end_address_id' => $endAddress->id,
        'distance_status' => 'pending',
    ]);
    $this->assertDatabaseHas('address_usages', [
        'user_id' => $user->id,
        'address_id' => $startAddress->id,
        'use_count' => 1,
    ]);
    $this->assertDatabaseHas('address_usages', [
        'user_id' => $user->id,
        'address_id' => $endAddress->id,
        'use_count' => 1,
    ]);

    Queue::assertPushed(CalculateRouteDistance::class);
});

it('backfills user address history from existing routes', function () {
    $user = User::factory()->create();
    $startAddress = Address::factory()->create();
    $endAddress = Address::factory()->create();

    Route::factory()->count(2)->create([
        'user_id' => $user->id,
        'vehicle_id' => $user->activeVehicle()->value('id'),
        'start_address_id' => $startAddress->id,
        'end_address_id' => $endAddress->id,
    ]);

    Schema::drop('address_usages');

    $migration = require database_path('migrations/2026_08_05_000000_create_address_usages_table.php');
    $migration->up();

    $this->assertDatabaseHas('address_usages', [
        'user_id' => $user->id,
        'address_id' => $startAddress->id,
        'use_count' => 2,
    ]);
    $this->assertDatabaseHas('address_usages', [
        'user_id' => $user->id,
        'address_id' => $endAddress->id,
        'use_count' => 2,
    ]);
});
