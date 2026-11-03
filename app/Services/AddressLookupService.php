<?php

namespace App\Services;

use App\Exceptions\AddressLookupException;
use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Collection;

class AddressLookupService
{
    /**
     * History matches that make a Google lookup unnecessary.
     */
    private const HISTORY_COVERS_INPUT = 5;

    private const MAX_SUGGESTIONS = 8;

    public function __construct(private readonly GoogleAddressService $google)
    {
        //
    }

    /**
     * The user's own address history comes first. Google is asked only when the history
     * has few matches, so new addresses with the same prefix still show up.
     *
     * @return array<int, array<string, string|null>>
     */
    public function autocomplete(User $user, string $input, ?string $sessionToken = null): array
    {
        $input = trim($input);

        if (mb_strlen($input) < 3) {
            return [];
        }

        $local = $this->localSuggestions($user, $input)
            ->map(fn (Address $address): array => $this->toSuggestion($address))
            ->values();

        if ($local->count() >= self::HISTORY_COVERS_INPUT) {
            return $local->all();
        }

        try {
            $google = $this->google->autocomplete($input, $sessionToken);
        } catch (AddressLookupException $exception) {
            if ($local->isEmpty()) {
                throw $exception;
            }

            return $local->all();
        }

        $known = $local->pluck('place_id')->all();

        return $local
            ->concat(collect($google)
                ->reject(fn (array $suggestion): bool => in_array($suggestion['place_id'], $known, true))
                ->map(fn (array $suggestion): array => [...$suggestion, 'source' => 'google']))
            ->take(self::MAX_SUGGESTIONS)
            ->values()
            ->all();
    }

    public function resolvePlace(User $user, string $placeId, ?string $sessionToken = null): Address
    {
        $placeId = trim($placeId);
        $address = null;

        if ($placeId !== '') {
            $address = Address::query()
                ->where('place_id', $placeId)
                ->first();
        }

        $address ??= $this->store($this->google->validatePlace($placeId, $sessionToken));

        $this->recordUsage($user, $address);

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    public function validatePlace(User $user, string $placeId, ?string $sessionToken = null): array
    {
        return $this->toPayload($this->resolvePlace($user, $placeId, $sessionToken));
    }

    /**
     * @return Collection<int, Address>
     */
    private function localSuggestions(User $user, string $input): Collection
    {
        return Address::query()
            ->select('addresses.*')
            ->join('address_usages', 'address_usages.address_id', '=', 'addresses.id')
            ->where('address_usages.user_id', $user->id)
            ->whereNotNull('place_id')
            ->matchingText($input, ['formatted_address', 'postal_code', 'city', 'street'])
            ->orderByDesc('address_usages.last_used_at')
            ->orderByDesc('address_usages.use_count')
            ->limit(self::MAX_SUGGESTIONS)
            ->get();
    }

    private function recordUsage(User $user, Address $address): void
    {
        $usage = $user->addressUsages()->firstOrCreate([
            'address_id' => $address->id,
        ], [
            'use_count' => 0,
            'last_used_at' => now(),
        ]);

        $usage->increment('use_count', 1, [
            'last_used_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function store(array $address): Address
    {
        return Address::updateOrCreate(
            ['place_id' => $address['place_id']],
            [
                'formatted_address' => $address['formatted_address'],
                'postal_code' => $address['postal_code'],
                'city' => $address['city'],
                'country' => $address['country'],
                'country_code' => $address['country_code'],
                'street' => $address['street'],
                'street_number' => $address['street_number'],
                'latitude' => $address['latitude'],
                'longitude' => $address['longitude'],
            ]
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function toSuggestion(Address $address): array
    {
        return [
            'place_id' => $address->place_id,
            'description' => $address->formatted_address,
            'main_text' => $this->mainText($address),
            'secondary_text' => collect([$address->postal_code, $address->city, $address->country])
                ->filter()
                ->implode(', '),
            'source' => 'history',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(Address $address): array
    {
        return [
            'place_id' => $address->place_id,
            'formatted_address' => $address->formatted_address,
            'postal_code' => $address->postal_code,
            'city' => $address->city,
            'country' => $address->country,
            'country_code' => $address->country_code,
            'street' => $address->street,
            'street_number' => $address->street_number,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ];
    }

    private function mainText(Address $address): string
    {
        $street = collect([$address->street, $address->street_number])
            ->filter()
            ->implode(' ');

        return $street !== '' ? $street : $address->formatted_address;
    }
}
