<?php

namespace App\Services;

use App\Enums\DistanceStatus;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregate numbers for the admin page. Only counts and totals: no per-user lists of
 * routes or addresses. The only emails shown are those of admins and moderators.
 */
class AdminMetricsService
{
    public const CACHE_KEY = 'admin:metrics';

    public const CACHE_MINUTES = 5;

    /** Free monthly Compute Routes Essentials requests are 10,000; warn well before that. */
    public const ROUTES_MONTHLY_WARNING = 8000;

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), fn (): array => $this->compute());
    }

    /**
     * @return array<string, mixed>
     */
    public function compute(): array
    {
        $now = CarbonImmutable::now();

        return [
            'generated_at' => $now->toDateTimeString(),
            'users' => $this->users($now),
            'routes' => $this->routes($now),
            'addresses' => $this->addresses($now),
            'reports' => $this->reports($now),
            'cache' => $this->cache(),
            'google' => $this->google($now),
            'queue' => $this->queue($now),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function users(CarbonImmutable $now): array
    {
        $users = DB::table('users');

        $byRole = array_fill_keys(UserRole::values(), 0);

        foreach ((clone $users)->select('role', DB::raw('COUNT(*) as total'))->groupBy('role')->get() as $row) {
            $byRole[$row->role] = (int) $row->total;
        }

        return [
            'total' => (clone $users)->count(),
            'new_7_days' => (clone $users)->where('created_at', '>=', $now->subDays(7))->count(),
            'new_30_days' => (clone $users)->where('created_at', '>=', $now->subDays(30))->count(),
            'active_today' => (clone $users)->where('last_active_at', '>=', $now->startOfDay())->count(),
            'active_7_days' => (clone $users)->where('last_active_at', '>=', $now->subDays(7))->count(),
            'active_30_days' => (clone $users)->where('last_active_at', '>=', $now->subDays(30))->count(),
            'by_role' => $byRole,
            'google' => DB::table('social_accounts')->where('provider', 'google')->distinct()->count('user_id'),
            'staff' => (clone $users)
                ->whereIn('role', [UserRole::Admin->value, UserRole::Moderator->value])
                ->orderBy('role')
                ->orderBy('email')
                ->get(['email', 'role'])
                ->map(fn ($row): array => ['email' => $row->email, 'role' => $row->role])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routes(CarbonImmutable $now): array
    {
        $routes = DB::table('routes');

        $byStatus = array_fill_keys(array_column(DistanceStatus::cases(), 'value'), 0);

        foreach ((clone $routes)->select('distance_status', DB::raw('COUNT(*) as total'))->groupBy('distance_status')->get() as $row) {
            $status = $row->distance_status ?? DistanceStatus::Pending->value;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + (int) $row->total;
        }

        return [
            'total' => (clone $routes)->count(),
            'today' => (clone $routes)->where('created_at', '>=', $now->startOfDay())->count(),
            'last_7_days' => (clone $routes)->where('created_at', '>=', $now->subDays(7))->count(),
            'last_30_days' => (clone $routes)->where('created_at', '>=', $now->subDays(30))->count(),
            'by_status' => $byStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addresses(CarbonImmutable $now): array
    {
        return [
            'total' => DB::table('addresses')->count(),
            'new_30_days' => DB::table('addresses')->where('created_at', '>=', $now->subDays(30))->count(),
            'top' => DB::table('address_usages')
                ->join('addresses', 'addresses.id', '=', 'address_usages.address_id')
                ->groupBy('addresses.id', 'addresses.formatted_address')
                ->orderByDesc('uses')
                ->orderBy('addresses.id')
                ->limit(10)
                ->get(['addresses.formatted_address', DB::raw('SUM(address_usages.use_count) as uses')])
                ->map(fn ($row): array => ['address' => $row->formatted_address, 'uses' => (int) $row->uses])
                ->all(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function reports(CarbonImmutable $now): array
    {
        return [
            'total' => DB::table('trip_reports')->count(),
            'last_30_days' => DB::table('trip_reports')->where('created_at', '>=', $now->subDays(30))->count(),
        ];
    }

    /**
     * Rows of the database cache store. Counts stay at zero when another store is used.
     *
     * @return array<string, int|bool>
     */
    private function cache(): array
    {
        $table = (string) config('cache.stores.database.table', 'cache');

        if (config('cache.default') !== 'database' || ! Schema::hasTable($table)) {
            return ['available' => false, 'distance_entries' => 0, 'total_rows' => 0];
        }

        $prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) config('cache.prefix'));

        return [
            'available' => true,
            'distance_entries' => DB::table($table)
                ->whereRaw("`key` LIKE ? ESCAPE '!'", [$prefix.'routes:distance:%'])
                ->where('expiration', '>', $this->nowTimestamp())
                ->count(),
            'total_rows' => DB::table($table)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function google(CarbonImmutable $now): array
    {
        $today = $this->usageSince($now->startOfDay());
        $month = $this->usageSince($now->startOfMonth());

        $routes = $month[ApiUsageCounter::ROUTES] ?? 0;
        $hits = $month[ApiUsageCounter::ROUTES_CACHE_HIT] ?? 0;

        return [
            'today' => $today,
            'month' => $month,
            'month_cache_hit_rate' => $routes + $hits > 0 ? round($hits / ($routes + $hits) * 100, 1) : null,
            'routes_month' => $routes,
            'routes_warning' => $routes > self::ROUTES_MONTHLY_WARNING,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function usageSince(CarbonImmutable $from): array
    {
        return DB::table('api_usage_daily')
            ->where('date', '>=', $from->toDateString())
            ->groupBy('service')
            ->orderBy('service')
            ->pluck(DB::raw('SUM(requests)'), 'service')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * @return array<string, int|null>
     */
    private function queue(CarbonImmutable $now): array
    {
        $jobs = (string) config('queue.connections.database.table', 'jobs');
        $failed = (string) config('queue.failed.table', 'failed_jobs');
        $oldest = Schema::hasTable($jobs) ? DB::table($jobs)->min('created_at') : null;

        return [
            'pending' => Schema::hasTable($jobs) ? DB::table($jobs)->count() : 0,
            'oldest_age_seconds' => $oldest === null ? null : max(0, $now->getTimestamp() - (int) $oldest),
            'failed' => Schema::hasTable($failed) ? DB::table($failed)->count() : 0,
        ];
    }

    private function nowTimestamp(): int
    {
        return CarbonImmutable::now()->getTimestamp();
    }
}
