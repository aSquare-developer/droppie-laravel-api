<?php

use App\Models\Address;
use App\Models\Route;
use App\Services\RouteReportService;

function unsavedRoute(int $id, string $date, float $distanceKm, string $from, string $to): Route
{
    $route = (new Route)->forceFill([
        'id' => $id,
        'started_at' => $date,
        'distance_km' => $distanceKm,
    ]);

    $route->setRelation('startAddress', (new Address)->forceFill(['formatted_address' => $from]));
    $route->setRelation('endAddress', (new Address)->forceFill(['formatted_address' => $to]));

    return $route;
}

it('builds sequential odometer readings from the starting mileage', function () {
    $routes = collect([
        unsavedRoute(1, '2026-06-01', 12.34, 'Tallinn', 'Maardu'),
        unsavedRoute(2, '2026-06-02', 0.06, 'Maardu', 'Maardu port'),
    ]);

    $rows = app(RouteReportService::class)->buildTripLogRows($routes, 1000.04);

    expect($rows->all())->toBe([
        [
            'route_id' => 1,
            'date' => '2026-06-01',
            'start_address' => 'Tallinn',
            'end_address' => 'Maardu',
            'distance_km' => 12.34,
            'odometer_start_km' => 1000.0,
            'odometer_end_km' => 1012.3,
        ],
        [
            'route_id' => 2,
            'date' => '2026-06-02',
            'start_address' => 'Maardu',
            'end_address' => 'Maardu port',
            'distance_km' => 0.06,
            'odometer_start_km' => 1012.3,
            'odometer_end_km' => 1012.4,
        ],
    ]);
});

it('uses the last row as ending odometer', function () {
    $service = app(RouteReportService::class);
    $rows = $service->buildTripLogRows(collect([unsavedRoute(1, '2026-06-01', 25.5, 'A', 'B')]), 100);

    expect($service->getEndingOdometer($rows, 100))->toBe(125.5);
});

it('keeps the starting mileage when there are no routes', function () {
    $service = app(RouteReportService::class);

    expect($service->buildTripLogRows(collect(), 99.96)->all())->toBe([])
        ->and($service->getEndingOdometer(collect(), 99.96))->toBe(100.0);
});
