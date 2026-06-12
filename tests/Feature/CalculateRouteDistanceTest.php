<?php

use App\Exceptions\GoogleRouteException;
use App\Jobs\CalculateRouteDistance;
use App\Models\Route;
use App\Models\User;
use App\Services\GoogleRouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks permanent google route errors as failed without retrying the job', function () {
    $route = Route::factory()->create([
        'user_id' => User::factory(),
        'distance_status' => 'pending',
    ]);

    $service = Mockery::mock(GoogleRouteService::class);
    $service
        ->shouldReceive('getDistanceInKm')
        ->once()
        ->andThrow(new GoogleRouteException(
            GoogleRouteException::NO_ROUTE,
            'No drivable route was found between the selected addresses.'
        ));

    (new CalculateRouteDistance($route))->handle($service);

    expect($route->fresh())
        ->distance_status->toBe('failed')
        ->distance_error->toBe('No drivable route was found between the selected addresses.');
});

it('throws retryable google route errors so the queue can retry the job', function () {
    $route = Route::factory()->create([
        'user_id' => User::factory(),
        'distance_status' => 'pending',
    ]);

    $service = Mockery::mock(GoogleRouteService::class);
    $service
        ->shouldReceive('getDistanceInKm')
        ->once()
        ->andThrow(new GoogleRouteException(
            GoogleRouteException::UNAVAILABLE,
            'Google Routes is temporarily unavailable. Distance calculation could not be completed.'
        ));

    expect(fn () => (new CalculateRouteDistance($route))->handle($service))
        ->toThrow(GoogleRouteException::class);

    expect($route->fresh()->distance_status)->toBe('processing');
});

it('does not expose unexpected technical errors to users', function () {
    $route = Route::factory()->create([
        'user_id' => User::factory(),
        'distance_status' => 'processing',
    ]);

    (new CalculateRouteDistance($route))->failed(new RuntimeException('Secret technical detail'));

    expect($route->fresh())
        ->distance_status->toBe('failed')
        ->distance_error->toBe('Unable to calculate route distance.');
});
