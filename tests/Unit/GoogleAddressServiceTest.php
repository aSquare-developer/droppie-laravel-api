<?php

use App\Exceptions\AddressLookupException;
use App\Exceptions\InvalidAddressException;
use App\Services\GoogleAddressService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.google.places_api_key', 'places-key');
    Config::set('services.google.address_validation_api_key', 'validation-key');
    Config::set('services.google.address_validation_enabled', false);
    Config::set('services.google.maps_language', 'et');
    Config::set('services.google.places_country', 'FI, ee');

    Http::preventStrayRequests();
});

function unitPlaceDetails(array $overrides = []): array
{
    return array_replace([
        'place_id' => 'place-tallinn',
        'formatted_address' => 'Viru väljak 4, 10111 Tallinn, Estonia',
        'address_components' => [
            ['long_name' => '4', 'short_name' => '4', 'types' => ['street_number']],
            ['long_name' => 'Viru väljak', 'short_name' => 'Viru väljak', 'types' => ['route']],
            ['long_name' => 'Tallinn', 'short_name' => 'Tallinn', 'types' => ['locality', 'political']],
            ['long_name' => '10111', 'short_name' => '10111', 'types' => ['postal_code']],
            ['long_name' => 'Estonia', 'short_name' => 'EE', 'types' => ['country', 'political']],
        ],
        'geometry' => ['location' => ['lat' => 59.4363, 'lng' => 24.7537]],
    ], $overrides);
}

function fakePlaceDetails(array $result): void
{
    Http::fake([
        'maps.googleapis.com/maps/api/place/details/json*' => Http::response(['status' => 'OK', 'result' => $result]),
    ]);
}

it('skips google for autocomplete input shorter than three characters', function () {
    expect(app(GoogleAddressService::class)->autocomplete('  Ta '))->toBe([]);

    Http::assertNothingSent();
});

it('sends language, session token and country filter with autocomplete requests', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/place/autocomplete/json*' => Http::response([
            'status' => 'OK',
            'predictions' => [
                [
                    'place_id' => 'place-1',
                    'description' => 'Viru väljak 4, Tallinn, Estonia',
                    'structured_formatting' => ['main_text' => 'Viru väljak 4', 'secondary_text' => 'Tallinn, Estonia'],
                ],
                ['place_id' => null, 'description' => 'Without a place id'],
                ['place_id' => 'place-2'],
            ],
        ]),
    ]);

    $suggestions = app(GoogleAddressService::class)->autocomplete(' Viru ', 'session-1');

    expect($suggestions)->toBe([[
        'place_id' => 'place-1',
        'description' => 'Viru väljak 4, Tallinn, Estonia',
        'main_text' => 'Viru väljak 4',
        'secondary_text' => 'Tallinn, Estonia',
    ]]);

    Http::assertSent(fn (Request $request): bool => $request['input'] === 'Viru'
        && $request['key'] === 'places-key'
        && $request['language'] === 'et'
        && $request['types'] === 'address'
        && $request['sessiontoken'] === 'session-1'
        && $request['components'] === 'country:fi|country:ee');
});

it('omits the country filter and session token when not given', function () {
    Config::set('services.google.places_country', '');
    Http::fake(['*' => Http::response(['status' => 'ZERO_RESULTS'])]);

    expect(app(GoogleAddressService::class)->autocomplete('Tallinn'))->toBe([]);

    Http::assertSent(fn (Request $request): bool => ! isset($request['components']) && ! isset($request['sessiontoken']));
});

it('reports google error statuses as lookup failures', function () {
    Http::fake(['*' => Http::response(['status' => 'REQUEST_DENIED', 'error_message' => 'The provided API key is invalid.'])]);

    expect(fn () => app(GoogleAddressService::class)->autocomplete('Tallinn'))
        ->toThrow(AddressLookupException::class, 'The provided API key is invalid.');
});

it('reports unsuccessful http responses as unavailable', function () {
    Http::fake(['*' => Http::response('Server error', 500)]);

    expect(fn () => app(GoogleAddressService::class)->autocomplete('Tallinn'))
        ->toThrow(AddressLookupException::class, 'Google Places Autocomplete is currently unavailable.');
});

it('requires a configured places api key', function () {
    Config::set('services.google.places_api_key', null);

    expect(fn () => app(GoogleAddressService::class)->autocomplete('Tallinn'))
        ->toThrow(AddressLookupException::class, 'Google Places API key is not configured.');

    Http::assertNothingSent();
});

it('rejects an empty place id without calling google', function () {
    expect(fn () => app(GoogleAddressService::class)->validatePlace('   '))
        ->toThrow(InvalidAddressException::class, 'Select an address from the suggestions.');

    Http::assertNothingSent();
});

it('treats unknown place ids as invalid addresses', function (string $status) {
    Http::fake(['*' => Http::response(['status' => $status])]);

    expect(fn () => app(GoogleAddressService::class)->validatePlace('missing-place'))
        ->toThrow(InvalidAddressException::class, 'Select an address from the suggestions.');
})->with(['INVALID_REQUEST', 'NOT_FOUND', 'ZERO_RESULTS']);

it('joins postal code suffixes and falls back to the postal town as city', function () {
    fakePlaceDetails(unitPlaceDetails([
        'address_components' => [
            ['long_name' => '10', 'types' => ['street_number']],
            ['long_name' => 'Downing Street', 'types' => ['route']],
            ['long_name' => 'London', 'types' => ['postal_town']],
            ['long_name' => 'SW1A', 'types' => ['postal_code']],
            ['long_name' => '2AA', 'types' => ['postal_code_suffix']],
            ['long_name' => 'United Kingdom', 'short_name' => 'GB', 'types' => ['country']],
        ],
    ]));

    $address = app(GoogleAddressService::class)->validatePlace('place-london');

    expect($address)
        ->postal_code->toBe('SW1A-2AA')
        ->city->toBe('London')
        ->country_code->toBe('GB');
});

it('falls back to the requested place id when google omits it', function () {
    $result = unitPlaceDetails();
    unset($result['place_id']);
    fakePlaceDetails($result);

    expect(app(GoogleAddressService::class)->validatePlace('requested-place')['place_id'])->toBe('requested-place');
});

it('lists every missing component once', function () {
    fakePlaceDetails(unitPlaceDetails(['geometry' => [], 'address_components' => []]));

    expect(fn () => app(GoogleAddressService::class)->validatePlace('place-tallinn'))
        ->toThrow(
            InvalidAddressException::class,
            'The selected address is missing: postal code, city, country, street, building number, coordinates.'
        );
});

it('confirms the address with the address validation api when enabled', function () {
    Config::set('services.google.address_validation_enabled', true);
    Http::fake([
        'maps.googleapis.com/*' => Http::response(['status' => 'OK', 'result' => unitPlaceDetails()]),
        'addressvalidation.googleapis.com/*' => Http::response(['result' => ['verdict' => ['addressComplete' => true]]]),
    ]);

    $address = app(GoogleAddressService::class)->validatePlace('place-tallinn');

    expect($address['city'])->toBe('Tallinn');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'addressvalidation')
        && $request->hasHeader('X-Goog-Api-Key', 'validation-key')
        && $request['address'] === [
            'regionCode' => 'EE',
            'locality' => 'Tallinn',
            'postalCode' => '10111',
            'addressLines' => ['Viru väljak 4'],
        ]);
});

it('rejects addresses the validation api cannot confirm', function (string $flag) {
    Config::set('services.google.address_validation_enabled', true);
    Http::fake([
        'maps.googleapis.com/*' => Http::response(['status' => 'OK', 'result' => unitPlaceDetails()]),
        'addressvalidation.googleapis.com/*' => Http::response(['result' => ['verdict' => [$flag => true]]]),
    ]);

    expect(fn () => app(GoogleAddressService::class)->validatePlace('place-tallinn'))
        ->toThrow(InvalidAddressException::class, 'Google could not confirm all address components.');
})->with(['hasUnconfirmedComponents', 'hasUnresolvedTokens', 'hasReplacedComponents']);
