<?php

use App\Services\ApiUsageCounter;
use App\Services\GoogleAddressService;
use App\Services\GoogleRouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function usage(string $service, ?string $date = null): int
{
    return (int) DB::table('api_usage_daily')
        ->where('date', $date ?? now()->toDateString())
        ->where('service', $service)
        ->value('requests');
}

it('adds up requests per service and day', function () {
    $counter = app(ApiUsageCounter::class);

    Carbon::setTestNow('2026-11-19 23:59:00');
    $counter->record('routes');
    $counter->record('routes');
    $counter->record('places_details');

    Carbon::setTestNow('2026-11-20 00:01:00');
    $counter->record('routes');

    expect(usage('routes', '2026-11-19'))->toBe(2)
        ->and(usage('places_details', '2026-11-19'))->toBe(1)
        ->and(usage('routes', '2026-11-20'))->toBe(1)
        ->and(DB::table('api_usage_daily')->count())->toBe(3);

    Carbon::setTestNow();
});

it('never breaks the request when the counter cannot be written', function () {
    // A real database error that, unlike dropping the table, keeps the test transaction:
    // the service name is longer than its column allows in strict mode.
    Log::spy();

    app(ApiUsageCounter::class)->record(str_repeat('x', 80));

    expect(DB::table('api_usage_daily')->count())->toBe(0);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'Could not record API usage.');
});

it('counts every google routes request and every distance cache hit', function () {
    Config::set('services.google.routes_api_key', 'routes-key');
    Http::fake(['routes.googleapis.com/*' => Http::response(['routes' => [['distanceMeters' => 12400]]])]);
    $service = app(GoogleRouteService::class);

    $service->getDistanceInKm('Tallinn', 'Tartu', 'place-a', 'place-b');
    $service->getDistanceInKm('Tallinn', 'Tartu', 'place-a', 'place-b');

    expect(usage(ApiUsageCounter::ROUTES))->toBe(1)
        ->and(usage(ApiUsageCounter::ROUTES_CACHE_HIT))->toBe(1);
});

it('counts failed routes requests too, since google bills them', function () {
    Config::set('services.google.routes_api_key', 'routes-key');
    Http::fake(['routes.googleapis.com/*' => Http::response('Unavailable', 503)]);

    expect(fn () => app(GoogleRouteService::class)->getDistanceInKm('Tallinn', 'Tartu'))->toThrow(RuntimeException::class);

    expect(usage(ApiUsageCounter::ROUTES))->toBe(1);
});

it('counts places autocomplete, details, find place and address validation requests', function () {
    Config::set('services.google.places_api_key', 'places-key');
    Config::set('services.google.address_validation_api_key', 'validation-key');
    Config::set('services.google.address_validation_enabled', true);
    Http::fake([
        'maps.googleapis.com/maps/api/place/autocomplete/*' => Http::response(['status' => 'ZERO_RESULTS']),
        'maps.googleapis.com/maps/api/place/findplacefromtext/*' => Http::response(['status' => 'ZERO_RESULTS']),
        'maps.googleapis.com/maps/api/place/details/*' => Http::response(['status' => 'OK', 'result' => [
            'place_id' => 'place-tallinn',
            'formatted_address' => 'Viru väljak 4, 10111 Tallinn, Estonia',
            'address_components' => [
                ['long_name' => '4', 'types' => ['street_number']],
                ['long_name' => 'Viru väljak', 'types' => ['route']],
                ['long_name' => 'Tallinn', 'types' => ['locality']],
                ['long_name' => '10111', 'types' => ['postal_code']],
                ['long_name' => 'Estonia', 'short_name' => 'EE', 'types' => ['country']],
            ],
            'geometry' => ['location' => ['lat' => 59.43, 'lng' => 24.75]],
        ]]),
        'addressvalidation.googleapis.com/*' => Http::response(['result' => ['verdict' => ['addressComplete' => true]]]),
    ]);
    $google = app(GoogleAddressService::class);

    $google->autocomplete('Viru väljak');
    $google->locateCity('Tallinn', 'EE');
    $google->validatePlace('place-tallinn');

    expect(usage(ApiUsageCounter::PLACES_AUTOCOMPLETE))->toBe(1)
        ->and(usage(ApiUsageCounter::PLACES_FIND_PLACE))->toBe(1)
        ->and(usage(ApiUsageCounter::PLACES_DETAILS))->toBe(1)
        ->and(usage(ApiUsageCounter::ADDRESS_VALIDATION))->toBe(1);
});
