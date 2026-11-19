<?php

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets overlap locks of scheduled tasks expire quickly', function (string $command, int $expiresAt) {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull()
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe($expiresAt);
})->with([
    'queue worker' => ['queue:work', 2],
    'stuck route recovery' => ['routes:recover-stuck', 15],
]);

it('prunes expired api tokens and old failed jobs every day', function (string $command, string $expression) {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe($expression);
})->with([
    'api tokens' => ['sanctum:prune-expired --hours=24', '17 3 * * *'],
    'failed jobs' => ['queue:prune-failed --hours=720', '27 3 * * *'],
]);

it('removes api tokens that expired more than a day ago', function () {
    $user = User::factory()->create();
    $old = $user->createToken('old', ['*'], now()->subDays(2))->accessToken;
    $recent = $user->createToken('recent', ['*'], now()->subHour())->accessToken;
    $active = $user->createToken('active', ['*'], now()->addDay())->accessToken;

    $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

    expect($old->fresh())->toBeNull()
        ->and($recent->fresh())->not->toBeNull()
        ->and($active->fresh())->not->toBeNull();
});
