<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a profile and active vehicle during registration', function () {
    $response = $this
        ->postJson('/api/register', [
            'name' => 'Mari',
            'email' => 'mari@example.com',
            'password' => 'password',
        ])
        ->assertCreated()
        ->assertJsonPath('user.name', 'Mari')
        ->assertJsonPath('user.email', 'mari@example.com');

    $userId = $response->json('user.id');

    $this->assertDatabaseHas('users', [
        'id' => $userId,
        'email' => 'mari@example.com',
    ]);

    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $userId,
        'first_name' => 'Mari',
    ]);

    $this->assertDatabaseHas('vehicles', [
        'user_id' => $userId,
        'is_active' => true,
    ]);
});
