<?php

use App\Services\GoogleRouteService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Config::set('services.google.routes_api_key', 'routes-key');
    Config::set('services.google.routes_retry_delay_ms', 0);

    Http::preventStrayRequests();
});

it('asks google only for the distance of a driving route between place ids', function () {
    Http::fake(['*' => Http::response(['routes' => [['distanceMeters' => 186_049]]])]);

    $distance = app(GoogleRouteService::class)->getDistanceInKm('Tallinn', 'Tartu', 'place-tallinn', 'place-tartu');

    expect($distance)->toBe(186.0);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Goog-Api-Key', 'routes-key')
        && $request->hasHeader('X-Goog-FieldMask', 'routes.distanceMeters')
        && $request['origin'] === ['placeId' => 'place-tallinn']
        && $request['destination'] === ['placeId' => 'place-tartu']
        && $request['travelMode'] === 'DRIVE');
});

it('falls back to address waypoints without place ids', function () {
    Http::fake(['*' => Http::response(['routes' => [['distanceMeters' => 1_250]]])]);

    expect(app(GoogleRouteService::class)->getDistanceInKm('Viru väljak 4, Tallinn', 'Raekoja plats 1, Tartu'))->toBe(1.3);

    Http::assertSent(fn (Request $request): bool => $request['origin'] === ['address' => 'Viru väljak 4, Tallinn']
        && $request['destination'] === ['address' => 'Raekoja plats 1, Tartu']);
});

it('serves a repeated route from the cache', function () {
    Http::fake(['*' => Http::response(['routes' => [['distanceMeters' => 10_000]]])]);
    $service = app(GoogleRouteService::class);

    $service->getDistanceInKm('Tallinn', 'Tartu', 'place-tallinn', 'place-tartu');
    $cached = $service->getDistanceInKm('Different text', 'Same places', 'place-tallinn', 'place-tartu');

    expect($cached)->toBe(10.0);
    Http::assertSentCount(1);
});

it('normalizes address text for the cache key when place ids are missing', function () {
    Http::fake(['*' => Http::response(['routes' => [['distanceMeters' => 10_000]]])]);
    $service = app(GoogleRouteService::class);

    $service->getDistanceInKm('Viru väljak 4,  Tallinn', 'Tartu');
    $service->getDistanceInKm('viru väljak 4, tallinn', '  TARTU ');

    Http::assertSentCount(1);
});

it('caches each direction separately', function () {
    Http::fake(['*' => Http::response(['routes' => [['distanceMeters' => 10_000]]])]);
    $service = app(GoogleRouteService::class);

    $service->getDistanceInKm('Tallinn', 'Tartu', 'place-tallinn', 'place-tartu');
    $service->getDistanceInKm('Tartu', 'Tallinn', 'place-tartu', 'place-tallinn');

    Http::assertSentCount(2);
});
