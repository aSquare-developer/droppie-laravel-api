<?php

use App\Enums\DistanceStatus;
use App\Exceptions\GoogleRouteException;
use App\Jobs\CalculateRouteDistance;
use App\Mail\FailureAlert;
use App\Models\Route;
use App\Models\User;
use App\Services\FailureAlerter;
use App\Services\GoogleRouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.alerts.email', 'admin@example.com');
    Config::set('services.alerts.throttle_minutes', 15);
    Mail::fake();
});

function pendingRoute(): Route
{
    return Route::factory()->create(['user_id' => User::factory(), 'distance_status' => 'pending']);
}

function googleRoutesFailing(GoogleRouteException $exception): GoogleRouteService
{
    $service = Mockery::mock(GoogleRouteService::class);
    $service->shouldReceive('getDistanceInKm')->andThrow($exception);

    return $service;
}

it('emails the administrator when a queued job has used up its retries', function () {
    Config::set('services.google.routes_api_key', 'routes-key');
    Http::fake(['routes.googleapis.com/*' => Http::response('Unavailable', 503)]);
    $route = pendingRoute();

    // The sync queue fails the job on its first error, like the last try on a real queue.
    expect(fn () => CalculateRouteDistance::dispatchSync($route))->toThrow(GoogleRouteException::class);

    expect($route->fresh()->distance_status)->toBe(DistanceStatus::Failed);
    Mail::assertSent(FailureAlert::class, fn (FailureAlert $mail): bool => $mail->hasTo('admin@example.com')
        && $mail->source === CalculateRouteDistance::class
        && $mail->exception instanceof GoogleRouteException);
});

it('emails the administrator when the google api key is missing or rejected', function (string $reason) {
    $route = pendingRoute();

    (new CalculateRouteDistance($route))->handle(googleRoutesFailing(new GoogleRouteException($reason, 'Rejected')));

    expect($route->fresh()->distance_status)->toBe(DistanceStatus::Failed);
    Mail::assertSent(FailureAlert::class, fn (FailureAlert $mail): bool => $mail->context === [
        'route_id' => $route->id,
        'reason' => $reason,
    ]);
})->with([GoogleRouteException::CONFIGURATION, GoogleRouteException::REQUEST_REJECTED]);

it('does not email when google finds no route between the chosen addresses', function () {
    (new CalculateRouteDistance(pendingRoute()))->handle(
        googleRoutesFailing(new GoogleRouteException(GoogleRouteException::NO_ROUTE, 'No route'))
    );

    Mail::assertNothingSent();
});

it('sends one alert per kind of failure within the throttle window and counts the rest', function () {
    $alerter = app(FailureAlerter::class);

    $alerter->alert('SomeJob', new RuntimeException('first'));
    $alerter->alert('SomeJob', new RuntimeException('second'));
    $alerter->alert('SomeJob', new RuntimeException('third'));
    $alerter->alert('OtherJob', new RuntimeException('other job'));

    Mail::assertSent(FailureAlert::class, 2);

    $this->travel(16)->minutes();
    $alerter->alert('SomeJob', new RuntimeException('after the window'));

    Mail::assertSent(FailureAlert::class, 3);
    Mail::assertSent(FailureAlert::class, fn (FailureAlert $mail): bool => $mail->exception->getMessage() === 'after the window'
        && $mail->suppressed === 2);
});

it('does nothing when no alert address is configured', function () {
    Config::set('services.alerts.email', null);

    app(FailureAlerter::class)->alert('SomeJob', new RuntimeException('boom'));

    Mail::assertNothingSent();
});

it('never lets a broken mailer break the job', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is down'));

    app(FailureAlerter::class)->alert('SomeJob', new RuntimeException('boom'));

    expect(true)->toBeTrue();
});

it('renders the alert as plain text with the failure details', function () {
    $mail = new FailureAlert(CalculateRouteDistance::class, new RuntimeException('Google said no'), ['route_id' => 7], 3, 15);

    $mail->assertHasSubject('['.config('app.name').'] Background job failed: CalculateRouteDistance');
    $mail->assertSeeInText('App\Jobs\CalculateRouteDistance');
    $mail->assertSeeInText('RuntimeException: Google said no');
    $mail->assertSeeInText('Route id: 7');
    $mail->assertSeeInText('3 similar failures happened since the previous alert.');
    $mail->assertSeeInText('not emailed for 15 minutes');
});
