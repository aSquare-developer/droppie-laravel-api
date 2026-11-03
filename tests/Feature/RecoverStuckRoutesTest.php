<?php

use App\Enums\DistanceStatus;
use App\Models\Route;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function routeWithStatus(User $user, string $status, string $updatedAt): Route
{
    $route = Route::factory()->create([
        'user_id' => $user->id,
        'distance_status' => $status,
    ]);

    Route::query()->whereKey($route->id)->toBase()->update(['updated_at' => $updatedAt]);

    return $route;
}

it('marks routes stuck for too long as failed so they can be changed again', function (string $status) {
    $user = User::factory()->create();
    $stuck = routeWithStatus($user, $status, now()->subMinutes(61));

    $this->artisan('routes:recover-stuck')->assertSuccessful();

    $stuck->refresh();

    expect($stuck->distance_status)->toBe(DistanceStatus::Failed);
    expect($stuck->distance_error)->not->toBeNull();
    expect($stuck->isDistanceCalculationInProgress())->toBeFalse();
})->with(['pending', 'processing']);

it('leaves recent, completed and failed routes untouched', function () {
    $user = User::factory()->create();
    $recent = routeWithStatus($user, 'processing', now()->subMinutes(5));
    $completed = routeWithStatus($user, 'completed', now()->subDays(2));
    $failed = routeWithStatus($user, 'failed', now()->subDays(2));

    $this->artisan('routes:recover-stuck')->assertSuccessful();

    expect($recent->fresh()->distance_status)->toBe(DistanceStatus::Processing);
    expect($completed->fresh()->distance_status)->toBe(DistanceStatus::Completed);
    expect($failed->fresh()->distance_status)->toBe(DistanceStatus::Failed);
    expect($failed->fresh()->distance_error)->toBeNull();
});

it('respects a custom timeout', function () {
    $user = User::factory()->create();
    $route = routeWithStatus($user, 'pending', now()->subMinutes(10));

    $this->artisan('routes:recover-stuck', ['--minutes' => 5])->assertSuccessful();

    expect($route->fresh()->distance_status)->toBe(DistanceStatus::Failed);
});

it('is scheduled to run regularly', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'routes:recover-stuck'));

    expect($events)->toHaveCount(1);
});
