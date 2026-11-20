<?php

use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('fills last_active_at from sessions, api tokens and routes', function () {
    // Running the migration is DDL, which commits MariaDB's test transaction, so the
    // database is rebuilt afterwards instead of rolled back.
    $this->beforeApplicationDestroyed(fn () => $this->artisan('migrate:fresh'));

    $migration = require database_path('migrations/2026_11_20_000100_add_last_active_at_to_users_table.php');

    $web = User::factory()->create();
    $api = User::factory()->create();
    $mixed = User::factory()->create();
    $idle = User::factory()->create();

    $migration->down();

    DB::table('sessions')->insert([
        ['id' => 's1', 'user_id' => $web->id, 'payload' => '', 'last_activity' => strtotime('2026-11-10 10:00:00')],
        ['id' => 's2', 'user_id' => $mixed->id, 'payload' => '', 'last_activity' => strtotime('2026-11-01 10:00:00')],
    ]);
    $api->createToken('a')->accessToken->forceFill(['last_used_at' => '2026-11-12 08:00:00'])->save();
    Route::factory()->create(['user_id' => $mixed->id, 'created_at' => '2026-11-15 09:30:00']);

    $migration->up();

    expect($web->fresh()->last_active_at?->toDateTimeString())->toBe('2026-11-10 10:00:00')
        ->and($api->fresh()->last_active_at?->toDateTimeString())->toBe('2026-11-12 08:00:00')
        ->and($mixed->fresh()->last_active_at?->toDateTimeString())->toBe('2026-11-15 09:30:00')
        ->and($idle->fresh()->last_active_at)->toBeNull();
});
