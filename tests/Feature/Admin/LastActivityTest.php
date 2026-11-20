<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('records activity on web requests at most once every five minutes', function () {
    $user = User::factory()->withProfile()->withVehicle()->create(['updated_at' => '2026-01-01 00:00:00']);

    $this->actingAs($user)->get(route('account'))->assertOk();
    expect($user->fresh()->last_active_at->toDateTimeString())->toBe('2026-11-20 10:00:00');

    Carbon::setTestNow('2026-11-20 10:04:00');
    $this->actingAs($user->fresh())->get(route('account'))->assertOk();
    expect($user->fresh()->last_active_at->toDateTimeString())->toBe('2026-11-20 10:00:00');

    Carbon::setTestNow('2026-11-20 10:06:00');
    $this->actingAs($user->fresh())->get(route('account'))->assertOk();
    expect($user->fresh()->last_active_at->toDateTimeString())->toBe('2026-11-20 10:06:00')
        ->and($user->fresh()->updated_at->toDateTimeString())->toBe('2026-01-01 00:00:00');
});

it('records activity on api requests with a sanctum token', function () {
    $user = User::factory()->withProfile()->create();

    $this->withToken($user->createToken('test')->plainTextToken)->getJson('/api/me')->assertOk();

    expect($user->fresh()->last_active_at?->toDateTimeString())->toBe('2026-11-20 10:00:00');
});

it('leaves guests alone', function () {
    $user = User::factory()->create();

    $this->get(route('login'))->assertOk();
    $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertUnauthorized();

    expect($user->fresh()->last_active_at)->toBeNull();
});
