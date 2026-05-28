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
            ->whereBetween('created_at', [
                $from . ' 00:00:00',
                $to . ' 23:59:59',
            ])
            ->where('distance_status', 'completed')
            ->orderBy('created_at')
            ->get();
    }

    public function getTotalDistance(Collection $routes): int
    {
        return $routes->sum('distance_km');
    }
}