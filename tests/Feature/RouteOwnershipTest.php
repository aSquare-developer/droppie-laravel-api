<?php

use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->route = Route::factory()->create([
        'user_id' => $this->owner->id,
        'distance_status' => 'completed',
    ]);
});

it('returns 404 for another user\'s route in the api', function () {
    $this->actingAs($this->stranger, 'sanctum');

    $this->getJson("/api/routes/{$this->route->id}")->assertNotFound();
    $this->putJson("/api/routes/{$this->route->id}", ['started_at' => '2026-06-02'])->assertNotFound();
    $this->deleteJson("/api/routes/{$this->route->id}")->assertNotFound();

    $this->assertDatabaseHas('routes', ['id' => $this->route->id]);
});

it('returns 404 for another user\'s route in the web interface', function () {
    $this->actingAs($this->stranger);

    $this->put(route('web.routes.update', $this->route->id), ['started_at' => '2026-06-02'])->assertNotFound();
    $this->delete(route('web.routes.destroy', $this->route->id))->assertNotFound();

    $this->assertDatabaseHas('routes', ['id' => $this->route->id]);
});

it('still lets the owner read their route', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->getJson("/api/routes/{$this->route->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->route->id);
});

it('returns 404 for a missing route', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/routes/999999')
        ->assertNotFound();
});
