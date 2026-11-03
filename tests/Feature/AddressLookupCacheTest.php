<?php

use App\Exceptions\AddressLookupException;
use App\Jobs\CalculateRouteDistance;
use App\Models\Address;
use App\Models\Route;
use App\Models\User;
use App\Services\GoogleAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function rememberAddress(User $user, array $attributes, int $useCount = 1): Address
{
    $address = Address::factory()->create($attributes);
    $user->addressUsages()->create([
        'address_id' => $address->id,
        'use_count' => $useCount,
        'last_used_at' => now(),
    ]);

    return $address;
}

function googleSuggestion(string $placeId, string $description): array
{
    return [
        'place_id' => $placeId,
        'description' => $description,
        'main_text' => strtok($description, ','),
        'secondary_text' => null,
    ];
}

it('lists history matches first and adds new google suggestions', function () {
    $user = User::factory()->create();
    rememberAddress($user, [
        'place_id' => 'known-place',
        'formatted_address' => 'Mannerheimintie 1, 00100 Helsinki, Finland',
        'postal_code' => '00100',
        'city' => 'Helsinki',
        'country' => 'Finland',
        'street' => 'Mannerheimintie',
        'street_number' => '1',
    ]);

    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock->shouldReceive('autocomplete')
            ->once()
            ->with('Mannerheimintie', 'session-1')
            ->andReturn([
                googleSuggestion('known-place', 'Mannerheimintie 1, Helsinki, Finland'),
                googleSuggestion('new-place', 'Mannerheimintie 5, Helsinki, Finland'),
            ]);
    });

    $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/addresses/autocomplete?input=Mannerheimintie&session_token=session-1')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.place_id', 'known-place')
        ->assertJsonPath('data.0.description', 'Mannerheimintie 1, 00100 Helsinki, Finland')
        ->assertJsonPath('data.0.main_text', 'Mannerheimintie 1')
        ->assertJsonPath('data.0.source', 'history')
        ->assertJsonPath('data.1.place_id', 'new-place')
        ->assertJsonPath('data.1.source', 'google');
});

it('skips google when the history already has enough matches', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $number) {
        rememberAddress($user, [
            'formatted_address' => "Mannerheimintie {$number}, 00100 Helsinki, Finland",
            'street' => 'Mannerheimintie',
            'street_number' => (string) $number,
        ]);
    }

    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock->shouldReceive('autocomplete')->never();
    });

    $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/addresses/autocomplete?input=Mannerheimintie')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

it('caps the merged suggestions at eight', function () {
    $user = User::factory()->create();
    rememberAddress($user, ['formatted_address' => 'Mannerheimintie 1, Helsinki', 'street' => 'Mannerheimintie']);

    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock->shouldReceive('autocomplete')->once()->andReturn(
            collect(range(10, 20))->map(fn (int $n) => googleSuggestion("google-{$n}", "Mannerheimintie {$n}, Helsinki"))->all()
        );
    });

    $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/addresses/autocomplete?input=Mannerheimintie')
        ->assertOk()
        ->assertJsonCount(8, 'data')
        ->assertJsonPath('data.0.source', 'history');
});

it('falls back to history when google is unavailable', function () {
    $user = User::factory()->create();
    rememberAddress($user, ['place_id' => 'known-place', 'formatted_address' => 'Mannerheimintie 1, Helsinki', 'street' => 'Mannerheimintie']);

    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock->shouldReceive('autocomplete')->once()
            ->andThrow(new AddressLookupException('Google Places Autocomplete is currently unavailable.'));
    });

    $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/addresses/autocomplete?input=Mannerheimintie')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.place_id', 'known-place');
});

it('reports google being unavailable when there is no history to show', function () {
    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock->shouldReceive('autocomplete')->once()
            ->andThrow(new AddressLookupException('Google Places Autocomplete is currently unavailable.'));
    });

    $this
        ->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/addresses/autocomplete?input=Mannerheimintie')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Google Places Autocomplete is currently unavailable.');
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
            ->twice()
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
