<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Route as TripRoute;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->loadMissing(['profile', 'activeVehicle']);
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', '-created_at');

        $query = $user
            ->routes()
            ->with(['startAddress', 'endAddress']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q
                    ->whereHas('startAddress', function ($addressQuery) use ($search): void {
                        $addressQuery
                            ->where('formatted_address', 'like', '%'.$search.'%')
                            ->orWhere('city', 'like', '%'.$search.'%')
                            ->orWhere('postal_code', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('endAddress', function ($addressQuery) use ($search): void {
                        $addressQuery
                            ->where('formatted_address', 'like', '%'.$search.'%')
                            ->orWhere('city', 'like', '%'.$search.'%')
                            ->orWhere('postal_code', 'like', '%'.$search.'%');
                    });
            });
        }

        $totalDistanceKm = (float) (clone $query)->sum('distance_km');

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowedSorts = ['created_at', 'distance_km', 'started_at'];

        if (! in_array($column, $allowedSorts, true)) {
            $sort = '-created_at';
            $column = 'created_at';
            $direction = 'desc';
        }

        $routes = $query
            ->orderBy($column, $direction)
            ->paginate(10)
            ->withQueryString();

        $editingRoute = null;

        if ($request->filled('edit')) {
            $editingRoute = TripRoute::query()
                ->with(['startAddress', 'endAddress'])
                ->findOrFail((int) $request->query('edit'));

            $this->authorize('update', $editingRoute);
        }

        return view('dashboard.index', [
            'user' => $user,
            'routes' => $routes,
            'summary' => [
                'total_distance_km' => round($totalDistanceKm, 1),
            ],
            'filters' => [
                'search' => $search,
                'sort' => $sort,
            ],
            'editingRoute' => $editingRoute,
            'statusLabels' => RouteStatusController::STATUS_LABELS,
        ]);
    }
}
