<?php

use App\Models\Address;
use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('matches addresses regardless of letter case and treats wildcards literally', function () {
    $tallinn = Address::factory()->create(['formatted_address' => 'Viru väljak 4, Tallinn, Estonia', 'city' => 'Tallinn']);
    $tartu = Address::factory()->create(['formatted_address' => 'Riia 1, Tartu, Estonia', 'city' => 'Tartu']);

    expect(Address::query()->matchingText('tallinn')->pluck('id')->all())->toBe([$tallinn->id]);
    expect(Address::query()->matchingText('TARTU')->pluck('id')->all())->toBe([$tartu->id]);
    expect(Address::query()->matchingText('%')->count())->toBe(0);
    expect(Address::query()->matchingText('t_llinn')->count())->toBe(0);
    expect(Address::query()->matchingText('!')->count())->toBe(0);
    expect(Address::query()->matchingText('\\')->count())->toBe(0);
});

it('matches the escape characters themselves literally', function () {
    $odd = Address::factory()->create(['formatted_address' => 'Pood "100%!" \\ Ladu_1, Tallinn', 'city' => 'Tallinn']);
    Address::factory()->create(['formatted_address' => 'Pood 100 Ladu 1, Tallinn', 'city' => 'Tallinn']);

    foreach (['100%!', '\\ ladu_1', 'LADU_1'] as $term) {
        expect(Address::query()->matchingText($term, ['formatted_address'])->pluck('id')->all())->toBe([$odd->id]);
    }
});

it('searches own routes case-insensitively through the api', function () {
    $user = User::factory()->create();
    $tallinn = Address::factory()->create(['formatted_address' => 'Viru väljak 4, Tallinn, Estonia', 'city' => 'Tallinn']);
    $tartu = Address::factory()->create(['formatted_address' => 'Riia 1, Tartu, Estonia', 'city' => 'Tartu']);
    $match = Route::factory()->create(['user_id' => $user->id, 'start_address_id' => $tallinn->id, 'end_address_id' => $tallinn->id]);
    Route::factory()->create(['user_id' => $user->id, 'start_address_id' => $tartu->id, 'end_address_id' => $tartu->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/routes?search=TALLINN')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});
