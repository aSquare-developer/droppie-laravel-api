<?php

namespace App\Http\Controllers\Web;

use App\Enums\DistanceStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $routes = $request
            ->user()
            ->routes()
            ->whereIn('id', $ids)
            ->get();

        return response()->json([
            'data' => $routes->map(fn ($route): array => [
                'id' => $route->id,
                'distance_km' => $route->distance_km,
                'distance_label' => $this->formatDistance($route->distance_km),
                'distance_status' => $route->distance_status,
                'distance_status_label' => ($route->distance_status ?? DistanceStatus::Pending)->label(),
                'distance_error' => $route->distance_error,
                'locked' => $route->isDistanceCalculationInProgress(),
            ])->values(),
        ]);
    }

    private function formatDistance(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not available';
        }

        return number_format((float) $value, 1, '.', ',').' km';
    }
}
