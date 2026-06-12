<?php

use App\Exceptions\GoogleRouteException;
use App\Services\GoogleRouteService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    Config::set('services.google.routes_api_key', 'test-key');
    Config::set('services.google.routes_max_attempts', 3);
    Config::set('services.google.routes_retry_delay_ms', 0);
});

it('retries transient google routes failures', function () {
    Http::fakeSequence()
        ->push(['error' => ['message' => 'Temporary failure']], 500)
        ->push(['routes' => [['distanceMeters' => 12_340]]]);

    $distance = app(GoogleRouteService::class)->getDistanceInKm(
        'Start address',
        'End address',
        'start-place',
        'end-place'
    );

    expect($distance)->toBe(12.3);
    Http::assertSentCount(2);
});

it('does not retry rejected google routes requests', function () {
    Http::fake([
        '*' => Http::response(['error' => ['message' => 'Invalid request']], 400),
    ]);

    $thrown = null;

    try {
        app(GoogleRouteService::class)->getDistanceInKm('Start address', 'End address');
    } catch (GoogleRouteException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(GoogleRouteException::class)
        ->and($thrown->reason)->toBe(GoogleRouteException::REQUEST_REJECTED)
        ->and($thrown->isRetryable())->toBeFalse()
        ->and($thrown->getMessage())->toBe('Google Routes could not calculate the distance for the selected addresses.');

    Http::assertSentCount(1);
});

it('returns a retryable error after transient attempts are exhausted', function () {
    Http::fake([
        '*' => Http::response(['error' => ['message' => 'Service unavailable']], 503),
    ]);

    $thrown = null;

    try {
        app(GoogleRouteService::class)->getDistanceInKm('Start address', 'End address');
    } catch (GoogleRouteException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(GoogleRouteException::class)
        ->and($thrown->reason)->toBe(GoogleRouteException::UNAVAILABLE)
        ->and($thrown->isRetryable())->toBeTrue()
        ->and($thrown->getMessage())->toBe('Google Routes is temporarily unavailable. Distance calculation could not be completed.');

    Http::assertSentCount(3);
});

it('retries connection failures', function () {
    Http::fakeSequence()
        ->pushFailedConnection('Connection timed out')
        ->push(['routes' => [['distanceMeters' => 4500]]]);

    $distance = app(GoogleRouteService::class)->getDistanceInKm('Start address', 'End address');

    expect($distance)->toBe(4.5);
    Http::assertSentCount(2);
});

it('returns a structured error when google finds no route', function () {
    Http::fake([
        '*' => Http::response(['routes' => []]),
    ]);

    expect(fn () => app(GoogleRouteService::class)->getDistanceInKm('Start address', 'End address'))
        ->toThrow(GoogleRouteException::class, 'No drivable route was found between the selected addresses.');
});

it('returns a structured error when the routes API key is missing', function () {
    Config::set('services.google.routes_api_key');
    Http::fake();

    expect(fn () => app(GoogleRouteService::class)->getDistanceInKm('Start address', 'End address'))
        ->toThrow(GoogleRouteException::class, 'Route distance calculation is not configured.');

    Http::assertNothingSent();
});
