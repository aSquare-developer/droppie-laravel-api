<?php

use App\Jobs\CalculateRouteDistance;
use App\Models\Address;
use App\Models\User;
use App\Services\GoogleAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('returns local address autocomplete suggestions before calling google', function () {
    $user = User::factory()->create();

    Address::factory()->create([
        'place_id' => 'known-place',
        'formatted_address' => 'Mannerheimintie 1, 00100 Helsinki, Finland',
        'postal_code' => '00100',
        'city' => 'Helsinki',
        'country' => 'Finland',
        'street' => 'Mannerheimintie',
        'street_number' => '1',
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

it('validates a known place id from local storage without calling google', function () {
    $user = User::factory()->create();

    Address::factory()->create([
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

    Queue::assertPushed(CalculateRouteDistance::class);
});
