<?php

namespace App\Services;

use App\Exceptions\AddressLookupException;
use App\Exceptions\InvalidAddressException;
use App\Jobs\CalculateRouteDistance;
use App\Models\Address;
use App\Models\Route;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RouteService
{
    public function __construct(private readonly AddressLookupService $addresses)
    {
        //
    }

    public function createRoute(User $user, array $data): Route
    {
        $vehicle = $user->activeVehicle()->first();

        if (! $vehicle) {
            throw ValidationException::withMessages([
                'vehicle' => 'Add an active vehicle to the profile before creating a route.',
            ]);
        }

        $route = $user->routes()->create([
            ...Arr::only($data, ['started_at']),
            'vehicle_id' => $vehicle->id,
            'start_address_id' => $this->resolveAddress('start', $data['start_place_id'], $data['start_address_session_token'] ?? null)->id,
            'end_address_id' => $this->resolveAddress('end', $data['end_place_id'], $data['end_address_session_token'] ?? null)->id,
            'distance_status' => 'pending',
        ]);

        CalculateRouteDistance::dispatch($route);

        return $route->load(['startAddress', 'endAddress']);
    }

    public function updateRoute(Route $route, array $data): Route
    {
        $this->ensureRouteCanBeChanged($route);

        $payload = Arr::only($data, ['started_at']);

        if (array_key_exists('start_place_id', $data)) {
            $payload = [
                ...$payload,
                'start_address_id' => $this->resolveAddress('start', $data['start_place_id'], $data['start_address_session_token'] ?? null)->id,
            ];
        }

        if (array_key_exists('end_place_id', $data)) {
            $payload = [
                ...$payload,
                'end_address_id' => $this->resolveAddress('end', $data['end_place_id'], $data['end_address_session_token'] ?? null)->id,
            ];
        }

        $route->fill($payload);

        $addressesChanged = $route->isDirty([
            'start_address_id',
            'end_address_id',
        ]);

        if ($addressesChanged) {
            $route->distance_km = null;
            $route->distance_status = 'pending';
            $route->distance_error = null;
        }

        $route->save();

        if ($addressesChanged) {
            CalculateRouteDistance::dispatch($route->fresh());
        }

        return $route->fresh(['startAddress', 'endAddress']);
    }

    public function deleteRoute(Route $route): void
    {
        $this->ensureRouteCanBeChanged($route);

        $route->delete();
    }

    private function ensureRouteCanBeChanged(Route $route): void
    {
        if ($route->isDistanceCalculationInProgress()) {
            throw new ConflictHttpException('Route cannot be edited or deleted while distance is being calculated.');
        }
    }

    private function resolveAddress(string $prefix, string $placeId, ?string $sessionToken): Address
    {
        try {
            return $this->addresses->resolvePlace($placeId, $sessionToken);
        } catch (InvalidAddressException $exception) {
            throw ValidationException::withMessages([
                $prefix.'_place_id' => $exception->getMessage(),
            ]);
        } catch (AddressLookupException $exception) {
            throw new HttpException(503, $exception->getMessage(), $exception);
        }
    }
}
