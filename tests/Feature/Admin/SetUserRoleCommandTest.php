<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('changes the role of the user with the given email', function () {
    $user = User::factory()->create(['email' => 'mari@example.com']);

    $this->artisan('users:set-role', ['email' => 'Mari@Example.com', 'role' => 'moderator'])
        ->expectsOutputToContain('mari@example.com: user -> moderator')
        ->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::Moderator);
});

it('rejects unknown roles and unknown users', function () {
    User::factory()->create(['email' => 'mari@example.com']);

    $this->artisan('users:set-role', ['email' => 'mari@example.com', 'role' => 'superuser'])
        ->expectsOutputToContain('Unknown role')
        ->assertFailed();

    $this->artisan('users:set-role', ['email' => 'nobody@example.com', 'role' => 'admin'])
        ->expectsOutputToContain('No user with that email address')
        ->assertFailed();
});

it('does not take the admin role from the last admin', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->artisan('users:set-role', ['email' => 'admin@example.com', 'role' => 'user'])
        ->expectsOutputToContain('last admin')
        ->assertFailed();

    expect($admin->fresh()->role)->toBe(UserRole::Admin);
});

it('lets an admin step down once another admin exists', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
    User::factory()->admin()->create();

    $this->artisan('users:set-role', ['email' => 'admin@example.com', 'role' => 'moderator'])
        ->assertSuccessful();

    expect($admin->fresh()->role)->toBe(UserRole::Moderator);
});

it('reports when the user already has the role', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->artisan('users:set-role', ['email' => 'admin@example.com', 'role' => 'admin'])
        ->expectsOutputToContain('already has the admin role')
        ->assertSuccessful();
});
