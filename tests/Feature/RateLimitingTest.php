<?php

use App\Models\User;
use App\Services\GoogleAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('limits repeated login attempts for the same email and IP address', function () {
    User::factory()->create([
        'email' => 'driver@example.com',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/login', [
            'email' => 'driver@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/login', [
        'email' => 'driver@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});

it('limits address autocomplete requests per authenticated user', function () {
    $user = User::factory()->create();

    $this->mock(GoogleAddressService::class, function ($mock): void {
        $mock
            ->shouldReceive('autocomplete')
            ->times(60)
            ->andReturn([]);
    });

    foreach (range(1, 60) as $attempt) {
        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/addresses/autocomplete?input=Helsinki')
            ->assertOk();
    }

    $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/addresses/autocomplete?input=Helsinki')
        ->assertTooManyRequests();
});

it('limits route write requests per authenticated user', function () {
    $user = User::factory()->create();

    foreach (range(1, 10) as $attempt) {
        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/routes', [])
            ->assertUnprocessable();
    }

    $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/routes', [])
        ->assertTooManyRequests();
});
