<?php

namespace App\Services;

use App\Exceptions\AddressLookupException;
use App\Exceptions\InvalidAddressException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAddressService
{
    /**
     * @return array<int, array<string, string|null>>
     */
    public function autocomplete(string $input, ?string $sessionToken = null): array
    {
        $input = trim($input);

        if (mb_strlen($input) < 3) {
            return [];
        }

        $params = [
            'input' => $input,
            'key' => $this->placesApiKey(),
            'language' => config('services.google.maps_language', 'ru'),
            'types' => 'address',
        ];

        if ($sessionToken) {
            $params['sessiontoken'] = $sessionToken;
        }

        if ($components = $this->countryComponents()) {
            $params['components'] = $components;
        }

        $payload = $this->googleGet(
            'https://maps.googleapis.com/maps/api/place/autocomplete/json',
            $params,
            'Google Places Autocomplete'
        );

        if (($payload['status'] ?? null) === 'ZERO_RESULTS') {
            return [];
        }

        $this->ensureGoogleStatus($payload, 'Google Places Autocomplete');

        return collect($payload['predictions'] ?? [])
            ->map(fn (array $prediction): array => [
                'place_id' => $prediction['place_id'] ?? null,
                'description' => $prediction['description'] ?? null,
                'main_text' => data_get($prediction, 'structured_formatting.main_text'),
                'secondary_text' => data_get($prediction, 'structured_formatting.secondary_text'),
            ])
            ->filter(fn (array $prediction): bool => filled($prediction['place_id']) && filled($prediction['description']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function validatePlace(string $placeId, ?string $sessionToken = null): array
    {
        $placeId = trim($placeId);

        if ($placeId === '') {
            throw new InvalidAddressException('Выберите адрес из списка подсказок.');
        }

        $params = [
            'place_id' => $placeId,
            'key' => $this->placesApiKey(),
            'fields' => 'place_id,formatted_address,address_component,geometry,name',
            'language' => config('services.google.maps_language', 'ru'),
        ];

        if ($sessionToken) {
            $params['sessiontoken'] = $sessionToken;
        }

        $payload = $this->googleGet(
            'https://maps.googleapis.com/maps/api/place/details/json',
            $params,
            'Google Place Details'
        );

        $this->ensureGoogleStatus($payload, 'Google Place Details', invalidStatuses: ['INVALID_REQUEST', 'NOT_FOUND', 'ZERO_RESULTS']);

        $result = $payload['result'] ?? null;

        if (! is_array($result)) {
            throw new InvalidAddressException('Google не вернул данные выбранного адреса.');
        }

        $address = $this->normalizePlaceResult($result, $placeId);
        $this->ensureCompleteAddress($address);
        $this->ensurePostalValidation($address);

        return $address;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function normalizePlaceResult(array $result, string $fallbackPlaceId): array
    {
        $components = collect($result['address_components'] ?? []);

        $component = function (array|string $types, string $field = 'long_name') use ($components): ?string {
            foreach ((array) $types as $type) {
                $match = $components->first(
                    fn (array $component): bool => in_array($type, $component['types'] ?? [], true)
                );

                if ($match) {
                    return $match[$field] ?? $match['long_name'] ?? null;
                }
            }

            return null;
        };

        $postalCode = $component('postal_code');
        $postalCodeSuffix = $component('postal_code_suffix');

        if ($postalCode && $postalCodeSuffix) {
            $postalCode = $postalCode.'-'.$postalCodeSuffix;
        }

        return [
            'place_id' => $result['place_id'] ?? $fallbackPlaceId,
            'formatted_address' => $result['formatted_address'] ?? null,
            'postal_code' => $postalCode,
            'city' => $component([
                'locality',
                'postal_town',
                'administrative_area_level_3',
                'administrative_area_level_2',
                'sublocality',
                'sublocality_level_1',
            ]),
            'country' => $component('country'),
            'country_code' => $component('country', 'short_name'),
            'street' => $component('route'),
            'street_number' => $component('street_number'),
            'latitude' => data_get($result, 'geometry.location.lat'),
            'longitude' => data_get($result, 'geometry.location.lng'),
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function ensureCompleteAddress(array $address): void
    {
        $required = [
            'formatted_address' => 'форматированный адрес',
            'postal_code' => 'почтовый индекс',
            'city' => 'город',
            'country' => 'страна',
            'street' => 'улица',
            'street_number' => 'номер дома',
            'latitude' => 'координаты',
            'longitude' => 'координаты',
        ];

        $missing = collect($required)
            ->filter(fn (string $label, string $key): bool => blank($address[$key] ?? null))
            ->values()
            ->unique()
            ->all();

        if ($missing !== []) {
            throw new InvalidAddressException(
                'В выбранном адресе отсутствует: '.implode(', ', $missing).'. Выберите точный адрес с индексом, городом, улицей и номером дома.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function ensurePostalValidation(array $address): void
    {
        if (! config('services.google.address_validation_enabled', false)) {
            return;
        }

        $payload = [
            'address' => [
                'regionCode' => $address['country_code'],
                'locality' => $address['city'],
                'postalCode' => $address['postal_code'],
                'addressLines' => [
                    Str::of($address['street'].' '.$address['street_number'])->squish()->toString(),
                ],
            ],
        ];

        $response = Http::timeout(8)
            ->withHeaders([
                'X-Goog-Api-Key' => $this->addressValidationApiKey(),
            ])
            ->post('https://addressvalidation.googleapis.com/v1:validateAddress', $payload);

        if (! $response->successful()) {
            throw new AddressLookupException('Google Address Validation API сейчас недоступен.');
        }

        $verdict = $response->json('result.verdict', []);

        if (
            ($verdict['hasUnconfirmedComponents'] ?? false)
            || ($verdict['hasUnresolvedTokens'] ?? false)
            || ($verdict['hasReplacedComponents'] ?? false)
        ) {
            throw new InvalidAddressException('Google не смог подтвердить все компоненты адреса. Проверьте индекс, город, улицу и номер дома.');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function googleGet(string $url, array $params, string $context): array
    {
        $response = Http::timeout(8)->get($url, $params);

        if (! $response->successful()) {
            throw new AddressLookupException($context.' сейчас недоступен.');
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $invalidStatuses
     */
    private function ensureGoogleStatus(array $payload, string $context, array $invalidStatuses = []): void
    {
        $status = $payload['status'] ?? null;

        if ($status === 'OK') {
            return;
        }

        $message = $payload['error_message'] ?? ($context.' вернул статус '.($status ?: 'UNKNOWN').'.');

        if (in_array($status, $invalidStatuses, true)) {
            throw new InvalidAddressException('Выберите адрес из списка подсказок.');
        }

        throw new AddressLookupException($message);
    }

    private function placesApiKey(): string
    {
        $key = config('services.google.places_api_key');

        if (blank($key)) {
            throw new AddressLookupException('Google Places API key не настроен.');
        }

        return $key;
    }

    private function addressValidationApiKey(): string
    {
        $key = config('services.google.address_validation_api_key');

        if (blank($key)) {
            throw new AddressLookupException('Google Address Validation API key не настроен.');
        }

        return $key;
    }

    private function countryComponents(): ?string
    {
        $countries = collect(explode(',', (string) config('services.google.places_country')))
            ->map(fn (string $country): string => strtolower(trim($country)))
            ->filter()
            ->map(fn (string $country): string => 'country:'.$country)
            ->implode('|');

        return $countries !== '' ? $countries : null;
    }
}
