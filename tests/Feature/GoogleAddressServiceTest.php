<?php

use App\Exceptions\InvalidAddressException;
use App\Services\GoogleAddressService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('normalizes a complete google place details response', function () {
    Config::set('services.google.places_api_key', 'test-key');

    Http::fake([
        'maps.googleapis.com/maps/api/place/details/json*' => Http::response([
            'status' => 'OK',
            'result' => googlePlaceResult(),
        ]),
    ]);

    $address = app(GoogleAddressService::class)->validatePlace('place-1', 'session-1');

    expect($address)
        ->place_id->toBe('place-1')
        ->formatted_address->toBe('Mannerheimintie 1, 00100 Helsinki, Finland')
        ->postal_code->toBe('00100')
        ->city->toBe('Helsinki')
        ->street->toBe('Mannerheimintie')
        ->street_number->toBe('1')
        ->country_code->toBe('FI')
        ->latitude->toBe(60.1699)
        ->longitude->toBe(24.9384);
});

it('rejects a google place without required address components', function () {
    Config::set('services.google.places_api_key', 'test-key');

    $result = googlePlaceResult();
    $result['address_components'] = array_values(array_filter(
        $result['address_components'],
        fn (array $component): bool => ! in_array('postal_code', $component['types'], true)
            && ! in_array('street_number', $component['types'], true)
    ));

    Http::fake([
        'maps.googleapis.com/maps/api/place/details/json*' => Http::response([
            'status' => 'OK',
            'result' => $result,
        ]),
    ]);

    expect(fn () => app(GoogleAddressService::class)->validatePlace('place-1'))
        ->toThrow(InvalidAddressException::class, 'postal code');
});

function googlePlaceResult(): array
{
    return [
        'place_id' => 'place-1',
        'formatted_address' => 'Mannerheimintie 1, 00100 Helsinki, Finland',
        'address_components' => [
            [
                'long_name' => '1',
                'short_name' => '1',
                'types' => ['street_number'],
            ],
            [
                'long_name' => 'Mannerheimintie',
                'short_name' => 'Mannerheimintie',
                'types' => ['route'],
            ],
            [
                'long_name' => 'Helsinki',
                'short_name' => 'Helsinki',
                'types' => ['locality', 'political'],
            ],
            [
                'long_name' => '00100',
                'short_name' => '00100',
                'types' => ['postal_code'],
            ],
            [
                'long_name' => 'Finland',
                'short_name' => 'FI',
                'types' => ['country', 'political'],
            ],
        ],
        'geometry' => [
            'location' => [
                'lat' => 60.1699,
                'lng' => 24.9384,
            ],
        ],
    ];
}
