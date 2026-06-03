<?php

namespace App\Services;

use App\Exceptions\AddressLookupException;
use App\Exceptions\InvalidAddressException;
use App\Jobs\CalculateRouteDistance;
use App\Models\Route;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RouteService
{
    public function __construct(private readonly GoogleAddressService $addresses)
    {
        //
    }

    public function createRoute(User $user, array $data): Route
    {
        $route = $user->routes()->create([
            ...Arr::only($data, ['started_at']),
            ...$this->resolveAddress('start', $data['start_place_id'], $data['start_address_session_token'] ?? null),
            ...$this->resolveAddress('end', $data['end_place_id'], $data['end_address_session_token'] ?? null),
            'distance_status' => 'pending',
        ]);

        CalculateRouteDistance::dispatch($route);

        return $route;
    }

    public function updateRoute(Route $route, array $data): Route
    {
        $payload = Arr::only($data, ['started_at']);

        if (array_key_exists('start_place_id', $data)) {
            $payload = [
                ...$payload,
                ...$this->resolveAddress('start', $data['start_place_id'], $data['start_address_session_token'] ?? null),
            ];
        }

        if (array_key_exists('end_place_id', $data)) {
            $payload = [
                ...$payload,
                ...$this->resolveAddress('end', $data['end_place_id'], $data['end_address_session_token'] ?? null),
            ];
        }

        $route->fill($payload);

        $addressesChanged = $route->isDirty([
            'start_place_id',
            'start_address',
            'end_place_id',
            'end_address',
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

        return $route->fresh();
    }

    public function deleteRoute(Route $route): void
    {
        $route->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAddress(string $prefix, string $placeId, ?string $sessionToken): array
    {
        try {
            $address = $this->addresses->validatePlace($placeId, $sessionToken);
        } catch (InvalidAddressException $exception) {
            throw ValidationException::withMessages([
                $prefix.'_place_id' => $exception->getMessage(),
            ]);
        } catch (AddressLookupException $exception) {
            throw new HttpException(503, $exception->getMessage(), $exception);
        }

        return [
            $prefix.'_place_id' => $address['place_id'],
            $prefix.'_address' => $address['formatted_address'],
            $prefix.'_postal_code' => $address['postal_code'],
            $prefix.'_city' => $address['city'],
            $prefix.'_country' => $address['country'],
            $prefix.'_country_code' => $address['country_code'],
            $prefix.'_street' => $address['street'],
            $prefix.'_street_number' => $address['street_number'],
            $prefix.'_latitude' => $address['latitude'],
            $prefix.'_longitude' => $address['longitude'],
        ];
    }
}
