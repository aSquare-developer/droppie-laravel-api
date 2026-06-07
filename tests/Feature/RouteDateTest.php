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

it('requires a trip date when creating a route', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/routes', [
            'start_place_id' => 'start-place',
            'end_place_id' => 'end-place',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('started_at');
});

it('requires selected google place ids instead of freeform addresses', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/routes', [
            'start_address' => 'Helsinki',
            'end_address' => 'Espoo',
            'started_at' => '2026-06-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'start_place_id',
            'end_place_id',
            'start_address',
            'end_address',
        ]);
});

it('stores the trip date and omits comments from route responses', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->mock(GoogleAddressService::class, function ($mock) {
        $mock
            ->shouldReceive('validatePlace')
            ->once()
            ->with('start-place', 'start-session')
            ->andReturn(routeAddress('start-place', 'Mannerheimintie 1, 00100 Helsinki, Finland'));

        $mock
            ->shouldReceive('validatePlace')
            ->once()
            ->with('end-place', 'end-session')
            ->andReturn(routeAddress('end-place', 'Otakaari 1, 02150 Espoo, Finland', city: 'Espoo', postalCode: '02150'));
    });

    $response = $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/routes', [
            'start_place_id' => 'start-place',
            'end_place_id' => 'end-place',
            'start_address_session_token' => 'start-session',
            'end_address_session_token' => 'end-session',
            'started_at' => '2026-06-01',
            'comment' => 'This should be ignored.',
        ])
        ->assertCreated();

    $data = $response->json('data');

    expect($data['started_at'])->toBe('2026-06-01');
    expect($data['start_address'])->toBe('Mannerheimintie 1, 00100 Helsinki, Finland');
    expect($data['start_place_id'])->toBe('start-place');
    expect($data['start_postal_code'])->toBe('00100');
    expect($data['start_city'])->toBe('Helsinki');
    expect($data['end_address'])->toBe('Otakaari 1, 02150 Espoo, Finland');
    expect($data['end_place_id'])->toBe('end-place');
    expect($data['end_postal_code'])->toBe('02150');
    expect($data['end_city'])->toBe('Espoo');
    expect(array_key_exists('comment', $data))->toBeFalse();
    expect(Schema::hasColumn('routes', 'comment'))->toBeFalse();
    expect(Schema::hasColumn('routes', 'start_address'))->toBeFalse();
    expect(Schema::hasColumn('routes', 'end_address'))->toBeFalse();
    expect(Schema::hasTable('addresses'))->toBeTrue();

    $this->assertDatabaseHas('routes', [
        'user_id' => $user->id,
        'started_at' => '2026-06-01 00:00:00',
    ]);

    $this->assertDatabaseHas('addresses', [
        'place_id' => 'start-place',
        'formatted_address' => 'Mannerheimintie 1, 00100 Helsinki, Finland',
        'postal_code' => '00100',
        'city' => 'Helsinki',
    ]);

    $this->assertDatabaseHas('addresses', [
        'place_id' => 'end-place',
        'formatted_address' => 'Otakaari 1, 02150 Espoo, Finland',
        'postal_code' => '02150',
        'city' => 'Espoo',
    ]);

    Queue::assertPushed(CalculateRouteDistance::class);
});

it('returns total distance across all matching routes', function () {
    $user = User::factory()->create();
    $startAddress = Address::factory()->create();
    $endAddress = Address::factory()->create();

    Route::factory()->count(12)->create([
        'user_id' => $user->id,
        'start_address_id' => $startAddress->id,
        'end_address_id' => $endAddress->id,
        'distance_km' => 2.5,
        'distance_status' => 'completed',
    ]);

    $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/routes')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('summary.total_distance_km', 30);
});

function routeAddress(
    string $placeId,
    string $formattedAddress,
    string $city = 'Helsinki',
    string $postalCode = '00100'
): array {
    return [
        'place_id' => $placeId,
        'formatted_address' => $formattedAddress,
        'postal_code' => $postalCode,
        'city' => $city,
        'country' => 'Finland',
        'country_code' => 'FI',
        'street' => 'Mannerheimintie',
        'street_number' => '1',
        'latitude' => 60.1699,
        'longitude' => 24.9384,
    ];
}
