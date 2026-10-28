<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('configures api tokens to expire', function () {
    expect(config('sanctum.expiration'))->toBeInt()->toBeGreaterThan(0);
});

it('rejects an expired api token and accepts a fresh one', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this->withToken($token)->getJson('/api/me')->assertOk();

    // The guard caches the user resolved by the first request within the same test.
    app('auth')->forgetGuards();

    $this->travel(config('sanctum.expiration') + 1)->minutes();

    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});
