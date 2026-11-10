<?php

namespace App\Services;

use App\Enums\DistanceStatus;
use App\Models\TripReport;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RouteReportService
{
    public function getOrCreateReport(User $user, string $from, string $to): TripReport
    {
        return DB::transaction(function () use ($user, $from, $to): TripReport {
            $vehicle = Vehicle::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            $existing = $vehicle ? TripReport::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereDate('period_from', $from)
                ->whereDate('period_to', $to)
                ->first() : null;

            if ($existing) {
                return $existing;
            }

            $this->validateTripLog($user, $vehicle);
            $this->ensureFollowsLatestReport($vehicle, $from);

            $routes = $user
                ->routes()
                ->with(['startAddress', 'endAddress'])
                ->where('vehicle_id', $vehicle->id)
                ->whereDate('started_at', '>=', $from)
                ->whereDate('started_at', '<=', $to)
                ->where('distance_status', DistanceStatus::Completed)
                ->orderBy('started_at')
                ->orderBy('id')
                ->get();

            $rows = $this->buildTripLogRows($routes, $vehicle->odometer_km);
            $endingOdometer = $this->getEndingOdometer($rows, $vehicle->odometer_km);

            $report = TripReport::query()->create([
                'user_id' => $user->id,
                'vehicle_id' => $vehicle->id,
                'period_from' => $from,
                'period_to' => $to,
                'odometer_start_km' => $vehicle->odometer_km,
                'odometer_end_km' => $endingOdometer,
                'total_distance_km' => round((float) $routes->sum('distance_km'), 1),
                'profile_snapshot' => [
                    'first_name' => $user->profile->first_name,
                    'last_name' => $user->profile->last_name,
                    'email' => $user->email,
                    'company_name' => $user->profile->company_name,
                    'country' => $user->profile->country,
                ],
                'vehicle_snapshot' => [
                    'registration_number' => $vehicle->registration_number,
                    'make_model' => $vehicle->make_model,
                ],
                'rows' => $rows->values()->all(),
                'generated_at' => now(),
            ]);

            if ($rows->isNotEmpty()) {
                $vehicle->update(['odometer_km' => $endingOdometer]);
            }

            return $report;
        });
    }

    /**
     * Delete the latest report of its vehicle and give its start reading back to the profile,
     * so the same period can be generated again. Older reports are kept: the profile mileage
     * has already moved past them.
     */
    public function deleteReport(TripReport $report): void
    {
        DB::transaction(function () use ($report): void {
            $vehicle = Vehicle::query()->lockForUpdate()->find($report->vehicle_id);
            $latest = $this->latestReport($report->vehicle_id);

            if ($latest?->id !== $report->id) {
                throw ValidationException::withMessages([
                    'report' => 'Only the latest report can be deleted.',
                ]);
            }

            // Leave a mileage that was changed by hand after the report.
            if ($vehicle !== null && (float) $vehicle->odometer_km === (float) $report->odometer_end_km) {
                $vehicle->update(['odometer_km' => $report->odometer_start_km]);
            }

            $report->delete();
        });
    }

    public function latestReport(int $vehicleId): ?TripReport
    {
        return TripReport::query()
            ->where('vehicle_id', $vehicleId)
            ->orderByDesc('period_to')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The profile mileage is the start reading of the next report and each report moves it to
     * its end reading, so a new report must start after the latest one. Overlapping or earlier
     * periods would count the same trips twice.
     */
    private function ensureFollowsLatestReport(Vehicle $vehicle, string $from): void
    {
        $latest = $this->latestReport($vehicle->id);

        if ($latest !== null && Carbon::parse($from)->lte($latest->period_to)) {
            throw ValidationException::withMessages([
                'from' => sprintf(
                    'Reports continue from the latest one (%s – %s). Choose a period starting after %s.',
                    $latest->period_from->format('d.m.Y'),
                    $latest->period_to->format('d.m.Y'),
                    $latest->period_to->format('d.m.Y'),
                ),
            ]);
        }
    }

    public function buildTripLogRows(Collection $routes, float $initialOdometer): Collection
    {
        $odometer = round($initialOdometer, 1);

        return $routes->map(function ($route) use (&$odometer): array {
            $startOdometer = $odometer;
            $odometer = round($startOdometer + (float) $route->distance_km, 1);

            return [
                'route_id' => $route->id,
                'date' => $route->started_at->toDateString(),
                'start_address' => $route->startAddress?->formatted_address,
                'end_address' => $route->endAddress?->formatted_address,
                'distance_km' => (float) $route->distance_km,
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

    private function validateTripLog(User $user, ?Vehicle $vehicle): void
    {
        $user->loadMissing('profile');
        $errors = [];

        if (blank($user->profile?->last_name)) {
            $errors['profile'][] = 'Add the driver last name to the profile.';
        }

        if (! $vehicle || blank($vehicle->registration_number)) {
            $errors['profile'][] = 'Add the vehicle registration number to the profile.';
        }

        if (! $vehicle || $vehicle->odometer_km === null) {
            $errors['profile'][] = 'Add the current vehicle mileage to the profile.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
