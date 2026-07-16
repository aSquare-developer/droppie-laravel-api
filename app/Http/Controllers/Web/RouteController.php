<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRouteRequest;
use App\Http\Requests\UpdateRouteRequest;
use App\Models\Route as TripRoute;
use App\Services\RouteService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RouteController extends Controller
{
    public function store(StoreRouteRequest $request, RouteService $routes): RedirectResponse
    {
        try {
            $routes->createRoute($request->user(), $request->validated());
        } catch (HttpException $exception) {
            return back()
                ->withErrors(['route' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Route added and queued for distance calculation.');
    }

    public function update(UpdateRouteRequest $request, TripRoute $route, RouteService $routes): RedirectResponse
    {
        $this->authorize('update', $route);

        try {
            $routes->updateRoute($route, $this->withoutUnchangedAddresses($route, $request->validated()));
        } catch (HttpException $exception) {
            return back()
                ->withErrors(['route' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Route updated.');
    }

    public function destroy(TripRoute $route, RouteService $routes): RedirectResponse
    {
        $this->authorize('delete', $route);

        try {
            $routes->deleteRoute($route);
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['route' => $exception->getMessage()]);
        }

        return back()->with('success', 'Route deleted.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutUnchangedAddresses(TripRoute $route, array $data): array
    {
        $route->loadMissing(['startAddress', 'endAddress']);

        if (($data['start_place_id'] ?? null) === $route->startAddress?->place_id) {
            unset($data['start_place_id'], $data['start_address_session_token']);
        }

        if (($data['end_place_id'] ?? null) === $route->endAddress?->place_id) {
            unset($data['end_place_id'], $data['end_address_session_token']);
        }

        return $data;
    }
}
