<?php

namespace App\Http\Controllers\Web;

use App\Enums\DistanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Route as TripRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->loadMissing('activeVehicle');
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 255);

        $routes = $user->routes();

        if ($search !== '') {
            $routes->where(function ($q) use ($search): void {
                $q
                    ->whereHas('startAddress', function ($addressQuery) use ($search): void {
                        $addressQuery->matchingText($search);
                    })
                    ->orWhereHas('endAddress', function ($addressQuery) use ($search): void {
                        $addressQuery->matchingText($search);
                    });
            });
        }

        $editingRoute = $request->filled('edit')
            ? $user->routes()->with(['startAddress', 'endAddress'])->findOrFail((int) $request->query('edit'))
            : null;

        // The history shows one trip date per page, newest first.
        $dates = (clone $routes)
            ->select('started_at')
            ->distinct()
            ->orderByDesc('started_at')
            ->pluck('started_at')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->values();

        $selectedDate = $this->selectedDate($request, $editingRoute) ?? $dates->first();

        $dayRoutes = $selectedDate === null ? collect() : (clone $routes)
            ->with(['startAddress', 'endAddress'])
            ->whereDate('started_at', $selectedDate)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        // New trips usually continue the last one entered, so start from its date.
        $defaultTripDate = $user->routes()
            ->latest('created_at')
            ->latest('id')
            ->first(['started_at'])
            ?->started_at
            ?->toDateString() ?? now()->toDateString();

        return view('dashboard.index', [
            'user' => $user,
            'dayRoutes' => $dayRoutes,
            'day' => [
                'date' => $selectedDate === null ? null : Carbon::parse($selectedDate),
                'distance_km' => round((float) $dayRoutes->sum('distance_km'), 1),
                'position' => $selectedDate === null ? null : $this->position($dates, $selectedDate),
                'count' => $dates->count(),
                'newer' => $selectedDate === null ? null : $dates->filter(fn (string $date): bool => $date > $selectedDate)->last(),
                'older' => $selectedDate === null ? null : $dates->first(fn (string $date): bool => $date < $selectedDate),
            ],
            'summary' => [
                'routes' => (clone $routes)->count(),
                'total_distance_km' => round((float) (clone $routes)->sum('distance_km'), 1),
                'in_progress' => (clone $routes)
                    ->whereIn('distance_status', [DistanceStatus::Pending, DistanceStatus::Processing])
                    ->count(),
            ],
            'filters' => [
                'search' => $search,
            ],
            'editingRoute' => $editingRoute,
            'defaultTripDate' => $defaultTripDate,
            'statusLabels' => DistanceStatus::labels(),
        ]);
    }

    private function selectedDate(Request $request, ?TripRoute $editingRoute): ?string
    {
        $date = (string) $request->query('date', '');

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return $date;
        }

        return $editingRoute?->started_at?->toDateString();
    }

    /**
     * @param  Collection<int, string>  $dates
     */
    private function position(Collection $dates, string $selectedDate): ?int
    {
        $index = $dates->search($selectedDate, true);

        return $index === false ? null : $index + 1;
    }
}
