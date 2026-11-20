<?php

use App\Models\Address;
use App\Models\AddressUsage;
use App\Models\Route;
use App\Models\User;
use App\Services\AdminMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-20 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('computes every metric on an empty database', function () {
    $metrics = app(AdminMetricsService::class)->compute();

    expect($metrics['users'])->toMatchArray([
        'total' => 0, 'new_7_days' => 0, 'active_today' => 0, 'google' => 0, 'staff' => [],
        'by_role' => ['user' => 0, 'moderator' => 0, 'admin' => 0],
    ])
        ->and($metrics['routes'])->toMatchArray([
            'total' => 0, 'today' => 0,
            'by_status' => ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0],
        ])
        ->and($metrics['addresses'])->toBe(['total' => 0, 'new_30_days' => 0, 'top' => []])
        ->and($metrics['reports'])->toBe(['total' => 0, 'last_30_days' => 0])
        ->and($metrics['google'])->toMatchArray(['today' => [], 'month' => [], 'month_cache_hit_rate' => null, 'routes_warning' => false])
        ->and($metrics['queue'])->toBe(['pending' => 0, 'oldest_age_seconds' => null, 'failed' => 0]);
});

it('renders the admin page on an empty database', function () {
    // The page also counts its viewer, so this is the smallest real case.
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
});

it('counts users by sign-up, activity, role and google sign-in', function () {
    User::factory()->create(['created_at' => '2026-11-19 10:00:00', 'last_active_at' => '2026-11-20 08:00:00']);
    User::factory()->create(['created_at' => '2026-11-01 10:00:00', 'last_active_at' => '2026-11-15 08:00:00']);
    $old = User::factory()->moderator()->create(['email' => 'mod@example.com', 'created_at' => '2026-01-01', 'last_active_at' => '2026-01-01']);
    User::factory()->admin()->create(['email' => 'admin@example.com', 'created_at' => '2026-01-01']);
    $old->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'g-1']);

    $users = app(AdminMetricsService::class)->compute()['users'];

    expect($users)->toMatchArray([
        'total' => 4,
        'new_7_days' => 1,
        'new_30_days' => 2,
        'active_today' => 1,
        'active_7_days' => 2,
        'active_30_days' => 2,
        'by_role' => ['user' => 2, 'moderator' => 1, 'admin' => 1],
        'google' => 1,
        'staff' => [
            ['email' => 'admin@example.com', 'role' => 'admin'],
            ['email' => 'mod@example.com', 'role' => 'moderator'],
        ],
    ]);
});

it('counts routes, addresses, reports, google usage and the queue', function () {
    $user = User::factory()->withVehicle()->create();
    $home = Address::factory()->create(['formatted_address' => 'Raatuse tn 18, Tartu', 'created_at' => '2026-11-18']);
    $work = Address::factory()->create(['formatted_address' => 'Riia tn 1, Tartu', 'created_at' => '2026-01-01']);
    AddressUsage::query()->create(['user_id' => $user->id, 'address_id' => $home->id, 'use_count' => 5, 'last_used_at' => now()]);
    AddressUsage::query()->create(['user_id' => User::factory()->create()->id, 'address_id' => $home->id, 'use_count' => 2, 'last_used_at' => now()]);
    AddressUsage::query()->create(['user_id' => $user->id, 'address_id' => $work->id, 'use_count' => 3, 'last_used_at' => now()]);

    Route::factory()->create(['user_id' => $user->id, 'distance_status' => 'completed', 'created_at' => '2026-11-20 09:00:00']);
    Route::factory()->create(['user_id' => $user->id, 'distance_status' => 'failed', 'created_at' => '2026-11-10 09:00:00']);
    Route::factory()->create(['user_id' => $user->id, 'distance_status' => 'pending', 'created_at' => '2026-09-01 09:00:00']);

    DB::table('api_usage_daily')->insert([
        ['date' => '2026-11-20', 'service' => 'routes', 'requests' => 3],
        ['date' => '2026-11-20', 'service' => 'routes_cache_hit', 'requests' => 1],
        ['date' => '2026-11-02', 'service' => 'routes', 'requests' => 8100],
        ['date' => '2026-11-02', 'service' => 'routes_cache_hit', 'requests' => 1896],
        ['date' => '2026-10-31', 'service' => 'routes', 'requests' => 500],
    ]);
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->getTimestamp(), 'created_at' => now()->subMinutes(3)->getTimestamp()]);

    $metrics = app(AdminMetricsService::class)->compute();

    expect($metrics['routes'])->toMatchArray([
        'total' => 3, 'today' => 1, 'last_7_days' => 1, 'last_30_days' => 2,
        'by_status' => ['pending' => 1, 'processing' => 0, 'completed' => 1, 'failed' => 1],
    ])
        ->and($metrics['addresses']['total'])->toBeGreaterThanOrEqual(2)
        ->and($metrics['addresses']['top'][0])->toBe(['address' => 'Raatuse tn 18, Tartu', 'uses' => 7])
        ->and($metrics['addresses']['top'][1])->toBe(['address' => 'Riia tn 1, Tartu', 'uses' => 3])
        ->and($metrics['google']['today'])->toBe(['routes' => 3, 'routes_cache_hit' => 1])
        ->and($metrics['google']['month'])->toBe(['routes' => 8103, 'routes_cache_hit' => 1897])
        ->and($metrics['google']['month_cache_hit_rate'])->toBe(19.0)
        ->and($metrics['google']['routes_warning'])->toBeTrue()
        ->and($metrics['queue'])->toBe(['pending' => 1, 'oldest_age_seconds' => 180, 'failed' => 0]);
});

it('counts live cached route distances when the database cache store is used', function () {
    Config::set('cache.default', 'database');
    Config::set('cache.prefix', 'droppie-cache-');
    DB::table('cache')->insert([
        ['key' => 'droppie-cache-routes:distance:aaa', 'value' => 's:4:"12.4";', 'expiration' => now()->addDay()->getTimestamp()],
        ['key' => 'droppie-cache-routes:distance:bbb', 'value' => 's:4:"10.0";', 'expiration' => now()->subMinute()->getTimestamp()],
        ['key' => 'droppie-cache-something-else', 'value' => 'i:1;', 'expiration' => now()->addDay()->getTimestamp()],
    ]);

    expect(app(AdminMetricsService::class)->compute()['cache'])
        ->toBe(['available' => true, 'distance_entries' => 1, 'total_rows' => 3]);
});

it('caches the metrics for five minutes', function () {
    $service = app(AdminMetricsService::class);

    expect($service->get()['users']['total'])->toBe(0);

    User::factory()->create();
    expect($service->get()['users']['total'])->toBe(0);

    $this->travel(6)->minutes();
    expect($service->get()['users']['total'])->toBe(1);

    Cache::forget(AdminMetricsService::CACHE_KEY);
});

it('shows only aggregates: no user emails besides staff, no routes per user', function () {
    $user = User::factory()->withProfile()->create(['email' => 'private@example.com']);
    Route::factory()->create(['user_id' => $user->id]);

    $this->actingAs(User::factory()->admin()->withProfile()->create())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('private@example.com');
});
