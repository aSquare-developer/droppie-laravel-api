<?php

use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents editing a route while distance calculation is active', function (string $status) {
    $user = User::factory()->create();
    $route = Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => '2026-06-01',
        'distance_status' => $status,
    ]);

    $this
        ->actingAs($user, 'sanctum')
        ->putJson("/api/routes/{$route->id}", [
            'started_at' => '2026-06-02',
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Route cannot be edited or deleted while distance is being calculated.');

    expect($route->fresh()->started_at->toDateString())->toBe('2026-06-01');
})->with(['pending', 'processing']);

it('prevents deleting a route while distance calculation is active', function (string $status) {
    $user = User::factory()->create();
    $route = Route::factory()->create([
        'user_id' => $user->id,
        'distance_status' => $status,
    ]);

    $this
        ->actingAs($user, 'sanctum')
        ->deleteJson("/api/routes/{$route->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Route cannot be edited or deleted while distance is being calculated.');

    $this->assertDatabaseHas('routes', [
        'id' => $route->id,
    ]);
})->with(['pending', 'processing']);

it('allows editing and deleting a route when distance calculation is not active', function (string $status) {
    $user = User::factory()->create();
    $route = Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => '2026-06-01',
        'distance_status' => $status,
    ]);

    $this
        ->actingAs($user, 'sanctum')
        ->putJson("/api/routes/{$route->id}", [
            'started_at' => '2026-06-02',
        ])
        ->assertOk();

    expect($route->fresh()->started_at->toDateString())->toBe('2026-06-02');

    $this
        ->actingAs($user, 'sanctum')
        ->deleteJson("/api/routes/{$route->id}")
        ->assertOk();

    $this->assertDatabaseMissing('routes', [
        'id' => $route->id,
    ]);
})->with(['completed', 'failed']);
