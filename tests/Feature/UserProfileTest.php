<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the authenticated user profile', function () {
    $user = User::factory()
        ->withProfile([
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
            'company_name' => 'Droppie Logistics',
            'country' => 'Finland',
        ])
        ->withVehicle([
            'registration_number' => 'ABC-123',
            'make_model' => 'Toyota Corolla',
            'odometer_km' => 150000,
        ])
        ->create([
            'email' => 'ivan@example.com',
        ]);

    $this
        ->actingAs($user, 'sanctum')
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('user.name', 'Ivan')
        ->assertJsonPath('user.last_name', 'Petrov')
        ->assertJsonPath('user.email', 'ivan@example.com')
        ->assertJsonPath('user.company_name', 'Droppie Logistics')
        ->assertJsonPath('user.car_registration_number', 'ABC-123')
        ->assertJsonPath('user.car_make_model', 'Toyota Corolla')
        ->assertJsonPath('user.car_mileage', 150000)
        ->assertJsonPath('user.country', 'Finland');
});

it('updates profile fields', function () {
    $user = User::factory()->create([
        'email' => 'driver@example.com',
    ]);

    $this
        ->actingAs($user, 'sanctum')
        ->patchJson('/api/profile', [
            'name' => 'Olga',
            'last_name' => 'Sokolova',
            'company_name' => 'Fast Drops',
            'car_registration_number' => 'XYZ-789',
            'car_make_model' => 'Ford Transit',
            'car_mileage' => 92000.5,
            'country' => 'Estonia',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Profile updated successfully')
        ->assertJsonPath('user.email', 'driver@example.com')
        ->assertJsonPath('user.name', 'Olga')
        ->assertJsonPath('user.last_name', 'Sokolova')
        ->assertJsonPath('user.company_name', 'Fast Drops')
        ->assertJsonPath('user.car_registration_number', 'XYZ-789')
        ->assertJsonPath('user.car_make_model', 'Ford Transit')
        ->assertJsonPath('user.car_mileage', 92000.5)
        ->assertJsonPath('user.country', 'Estonia');

    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'first_name' => 'Olga',
        'last_name' => 'Sokolova',
        'company_name' => 'Fast Drops',
        'country' => 'Estonia',
    ]);

    $this->assertDatabaseHas('vehicles', [
        'user_id' => $user->id,
        'is_active' => true,
        'registration_number' => 'XYZ-789',
        'make_model' => 'Ford Transit',
        'odometer_km' => 92000.5,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'driver@example.com',
    ]);
});

it('does not allow changing profile email', function () {
    $user = User::factory()->create([
        'email' => 'old@example.com',
    ]);

    $this
        ->actingAs($user, 'sanctum')
        ->patchJson('/api/profile', [
            'email' => 'new@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'old@example.com',
    ]);
});
