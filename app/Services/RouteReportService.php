<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
            ->orderBy('id')
            ->get();
    }

    public function getTotalDistance(Collection $routes): float
    {
        return round((float) $routes->sum('distance_km'), 1);
    }

    public function validateTripLog(User $user): void
    {
        $errors = [];

        if (blank($user->last_name)) {
            $errors['profile'][] = 'Add the driver last name to the profile.';
        }

        if (blank($user->car_registration_number)) {
            $errors['profile'][] = 'Add the vehicle registration number to the profile.';
        }

        if ($user->car_mileage === null) {
            $errors['profile'][] = 'Add the current vehicle mileage to the profile.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function buildTripLogRows(Collection $routes, float $initialOdometer): Collection
    {
        $odometer = round($initialOdometer, 1);

        return $routes->map(function ($route) use (&$odometer): array {
            $startOdometer = $odometer;
            $odometer = round($startOdometer + (float) $route->distance_km, 1);

            return [
                'route' => $route,
                'odometer_start_km' => $startOdometer,
                'odometer_end_km' => $odometer,
            ];
        });
    }

    public function getEndingOdometer(Collection $tripLogRows, float $initialOdometer): float
    {
        $lastRow = $tripLogRows->last();

        return (float) ($lastRow['odometer_end_km'] ?? round($initialOdometer, 1));
    }
}
