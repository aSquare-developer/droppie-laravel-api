<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class RouteReportService
{
    public function getRoutesForPeriod(User $user, string $from, string $to): Collection
    {
        return $user
            ->routes()
            ->with(['startAddress', 'endAddress'])
            ->whereDate('started_at', '>=', $from)
            ->whereDate('started_at', '<=', $to)
            ->where('distance_status', 'completed')
            ->orderBy('started_at')
            ->get();
    }

    public function getTotalDistance(Collection $routes): float
    {
        return round((float) $routes->sum('distance_km'), 1);
    }
}
