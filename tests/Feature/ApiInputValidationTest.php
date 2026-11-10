<?php

use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects invalid route list filters with 422', function (array $query, string $field) {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/routes?'.http_build_query($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'non-numeric min' => [['min_distance' => 'abc'], 'min_distance'],
    'non-numeric max' => [['max_distance' => 'abc'], 'max_distance'],
    'negative min' => [['min_distance' => -1], 'min_distance'],
    'max below min' => [['min_distance' => 10, 'max_distance' => 5], 'max_distance'],
    'unknown sort' => [['sort' => 'password'], 'sort'],
    'long search' => [['search' => str_repeat('a', 256)], 'search'],
    'bad page' => [['page' => 0], 'page'],
]);

it('filters routes by a valid distance range', function () {
    $user = User::factory()->create();
    $short = Route::factory()->create(['user_id' => $user->id, 'distance_km' => 3]);
    $match = Route::factory()->create(['user_id' => $user->id, 'distance_km' => 12.5]);
    Route::factory()->create(['user_id' => $user->id, 'distance_km' => 50]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/routes?min_distance=5&max_distance=20&sort=-distance_km&page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/routes?max_distance=5')
        ->assertOk()
        ->assertJsonPath('data.0.id', $short->id);
});

it('rejects overlong registration fields with 422', function (string $field, string $value) {
    $payload = array_merge(['name' => 'Mari', 'email' => 'mari@example.com', 'password' => 'secret12'], [$field => $value]);

    $this->postJson('/api/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'name' => ['name', str_repeat('a', 256)],
    'email' => ['email', str_repeat('a', 250).'@example.com'],
    'password' => ['password', str_repeat('a', 256)],
]);
