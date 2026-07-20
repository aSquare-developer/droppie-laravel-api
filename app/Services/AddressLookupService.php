<?php

namespace App\Services;

use App\Models\Address;
use Illuminate\Support\Collection;

class AddressLookupService
{
    public function __construct(private readonly GoogleAddressService $google)
    {
        //
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public function autocomplete(string $input, ?string $sessionToken = null): array
    {
        $input = trim($input);

        if (mb_strlen($input) < 3) {
            return [];
        }

        $local = $this->localSuggestions($input);

        if ($local->isNotEmpty()) {
            return $local
                ->map(fn (Address $address): array => $this->toSuggestion($address))
                ->values()
                ->all();
        }

        return $this->google->autocomplete($input, $sessionToken);
    }

    public function resolvePlace(string $placeId, ?string $sessionToken = null): Address
    {
        $placeId = trim($placeId);

        if ($placeId !== '') {
            $local = Address::query()
                ->where('place_id', $placeId)
                ->first();

            if ($local) {
                return $local;
            }
        }

        return $this->store($this->google->validatePlace($placeId, $sessionToken));
    }

    /**
     * @return array<string, mixed>
     */
    public function validatePlace(string $placeId, ?string $sessionToken = null): array
    {
        return $this->toPayload($this->resolvePlace($placeId, $sessionToken));
    }

    /**
     * @return Collection<int, Address>
     */
    private function localSuggestions(string $input): Collection
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $input).'%';

        return Address::query()
            ->whereNotNull('place_id')
            ->where(function ($query) use ($like): void {
                $query
                    ->where('formatted_address', 'like', $like)
                    ->orWhere('postal_code', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('street', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();
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
