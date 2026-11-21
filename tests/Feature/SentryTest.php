<?php

use App\Exceptions\GoogleRouteException;
use App\Jobs\CalculateRouteDistance;
use App\Models\Route as TripRoute;
use App\Models\User;
use App\Services\GoogleRouteService;
use App\Support\SentryScrubber;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\UserDataBag;
use Tests\Support\SentrySpy;

uses(RefreshDatabase::class);

it('stays off without a dsn, as in tests, CI and local development', function () {
    expect(config('sentry.dsn'))->toBeEmpty()
        ->and(SentrySdk::getCurrentHub()->getClient()?->getOptions()->getDsn())->toBeNull();
});

it('uses safe defaults for personal data, request bodies and sampling', function () {
    $options = SentrySdk::getCurrentHub()->getClient()->getOptions();

    expect($options->shouldSendDefaultPii())->toBeFalse()
        ->and($options->getMaxRequestBodySize())->toBe('never')
        ->and($options->getEnvironment())->toBe('testing')
        ->and(config('sentry.traces_sample_rate'))->toBe(0.1);
});

it('takes the release from the REVISION file written by the deploy', function () {
    $file = base_path('REVISION');
    expect(file_exists($file))->toBeFalse();

    file_put_contents($file, "abc1234\n");

    try {
        expect((require config_path('sentry.php'))['release'])->toBe('abc1234');
    } finally {
        unlink($file);
    }

    expect((require config_path('sentry.php'))['release'])->toBeNull();
});

it('reports a web error with only the user id and no secrets', function () {
    $sentry = SentrySpy::install();
    $user = User::factory()->withProfile()->create(['email' => 'mari@example.com']);

    Route::middleware('web')->post('/__sentry-test', function () {
        throw new RuntimeException('Boom with https://maps.googleapis.com/maps/api/place/details/json?place_id=1&key=AIzaSyA1234567890abcdefghijklmnopqrstuv');
    });

    $this->actingAs($user)
        ->withHeader('Authorization', 'Bearer 12|plain-sanctum-token')
        ->withCookie('droppie_session', 'cookie-value')
        ->post('/__sentry-test?token=query-secret', ['email' => 'mari@example.com', 'password' => 'hunter2-password'])
        ->assertServerError();

    expect($sentry->errors())->toHaveCount(1);
    $event = $sentry->errors()[0];
    $sent = json_encode([
        $event->getRequest(), $event->getExceptions()[0]->getValue(), $event->getExtra(), $event->getContexts(),
        $event->getUser()?->getEmail(), $event->getUser()?->getIpAddress(),
    ]);

    expect($event->getUser()?->getId())->toBe($user->id)
        ->and($event->getUser()?->getEmail())->toBeNull()
        ->and($event->getUser()?->getIpAddress())->toBeNull()
        ->and($event->getRequest())->not->toHaveKey('data')
        ->and($sent)->not->toContain('hunter2-password')
        ->and($sent)->not->toContain('plain-sanctum-token')
        ->and($sent)->not->toContain('cookie-value')
        ->and($sent)->not->toContain('query-secret')
        ->and($sent)->not->toContain('AIzaSyA1234567890')
        ->and($sent)->not->toContain('mari@example.com');
});

it('reports a route job only once its retries are used up', function () {
    $sentry = SentrySpy::install();
    Config::set('services.google.routes_api_key', 'routes-key');
    Http::fake(['routes.googleapis.com/*' => Http::response('Unavailable', 503)]);
    Mail::fake();
    $route = TripRoute::factory()->create(['user_id' => User::factory(), 'distance_status' => 'pending']);

    CalculateRouteDistance::dispatch($route)->onConnection('database');

    // Three tries with 15 s and 60 s backoff, run by the real queue worker.
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true]);
    expect($sentry->errors())->toBeEmpty();

    $this->travel(16)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true]);
    expect($sentry->errors())->toBeEmpty();

    $this->travel(61)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true]);

    expect($sentry->errors())->toHaveCount(1)
        ->and($sentry->errors()[0]->getExceptions()[0]->getType())->toBe(GoogleRouteException::class)
        ->and(DB::table('failed_jobs')->count())->toBe(1);
});

it('reports a rejected google request but not a route google cannot find', function (string $reason, int $expected) {
    $sentry = SentrySpy::install();
    Mail::fake();
    $service = Mockery::mock(GoogleRouteService::class);
    $service->shouldReceive('getDistanceInKm')->andThrow(new GoogleRouteException($reason, 'Google said no'));

    (new CalculateRouteDistance(TripRoute::factory()->create(['user_id' => User::factory()])))->handle($service);

    expect($sentry->errors())->toHaveCount($expected);
})->with([
    'no route (expected state)' => [GoogleRouteException::NO_ROUTE, 0],
    'rejected request' => [GoogleRouteException::REQUEST_REJECTED, 1],
    'missing api key' => [GoogleRouteException::CONFIGURATION, 1],
]);

it('monitors the scheduled tasks with sentry crons', function () {
    $events = collect(app(Schedule::class)->events())->keyBy(fn ($event) => (string) $event->command);

    foreach (['queue:work', 'routes:recover-stuck', 'sanctum:prune-expired', 'queue:prune-failed'] as $command) {
        $event = $events->first(fn ($event, string $key): bool => str_contains($key, $command));
        $before = (new ReflectionProperty($event, 'beforeCallbacks'))->getValue($event);

        expect($before)->not->toBeEmpty("{$command} has no Sentry check-in");
    }
});

it('scrubs secrets from text and nested data', function () {
    expect(SentryScrubber::scrubText('GET https://maps.googleapis.com/x?input=Tartu&key=AIzaSyA1234567890abcdefghijklmnopqrstuv&sessiontoken=abc'))
        ->toBe('GET https://maps.googleapis.com/x?input=Tartu&key=[Filtered]&sessiontoken=[Filtered]')
        ->and(SentryScrubber::scrubText('Authorization: Bearer 5|abcdef'))->toBe('Authorization: Bearer [Filtered]')
        ->and(SentryScrubber::scrubText("SQLSTATE[23000]: Duplicate (Connection: mariadb, SQL: insert into `users` (`email`, `password`) values ('mari@example.com', '\$2y\$04\$hash'))"))
        ->toBe('SQLSTATE[23000]: Duplicate (Connection: mariadb, SQL: insert into `users` (`email`, `password`) values (?, ?))')
        ->and(SentryScrubber::scrubArray(['headers' => ['authorization' => ['x'], 'accept' => 'json'], 'password' => 'p', 'nested' => ['api_key' => 'k', 'ok' => 1]]))
        ->toBe(['headers' => ['authorization' => '[Filtered]', 'accept' => 'json'], 'password' => '[Filtered]', 'nested' => ['api_key' => '[Filtered]', 'ok' => 1]]);
});

it('strips bodies, cookies, auth headers and keys from the request data of an event', function () {
    // In tests the SDK attaches no request (it skips console runs), so build one as in production.
    $event = Event::createEvent();
    $event->setRequest([
        'url' => 'https://droppie.test/api/routes?token=query-secret',
        'method' => 'POST',
        'query_string' => 'token=query-secret&page=2',
        'headers' => ['authorization' => ['Bearer 12|plain-sanctum-token'], 'cookie' => ['droppie_session=abc'], 'accept' => ['application/json']],
        'cookies' => ['droppie_session' => 'abc'],
        'data' => ['email' => 'mari@example.com', 'password' => 'hunter2-password'],
        'env' => ['REMOTE_ADDR' => '203.0.113.5'],
    ]);
    $event->setUser(new UserDataBag(7, 'mari@example.com', '203.0.113.5', 'mari'));

    $scrubbed = SentryScrubber::beforeSend($event);

    expect($scrubbed->getRequest())->toBe([
        'url' => 'https://droppie.test/api/routes?token=[Filtered]',
        'method' => 'POST',
        'query_string' => 'token=[Filtered]&page=2',
        'headers' => ['authorization' => '[Filtered]', 'cookie' => '[Filtered]', 'accept' => ['application/json']],
    ])
        ->and($scrubbed->getUser()->getId())->toBe(7)
        ->and($scrubbed->getUser()->getEmail())->toBeNull()
        ->and($scrubbed->getUser()->getIpAddress())->toBeNull()
        ->and($scrubbed->getUser()->getUsername())->toBeNull();
});

it('strips google keys from http client breadcrumbs and spans', function () {
    $breadcrumb = SentryScrubber::beforeBreadcrumb(new Breadcrumb('info', 'http', 'http', null, [
        'url' => 'https://maps.googleapis.com/maps/api/place/autocomplete/json?input=Tartu&key=AIzaSyA1234567890abcdefghijklmnopqrstuv',
    ]));

    $transaction = Event::createTransaction();
    $span = new Span;
    $span->setDescription('GET https://maps.googleapis.com/maps/api/place/details/json?place_id=1&key=AIzaSyA1234567890abcdefghijklmnopqrstuv');
    $span->setData(['url' => 'https://maps.googleapis.com/x?key=AIzaSyA1234567890abcdefghijklmnopqrstuv']);
    $transaction->setSpans([$span]);
    $scrubbed = SentryScrubber::beforeSendTransaction($transaction);

    expect($breadcrumb->getMetadata()['url'])->toBe('https://maps.googleapis.com/maps/api/place/autocomplete/json?input=Tartu&key=[Filtered]')
        ->and($scrubbed->getSpans()[0]->getDescription())->toBe('GET https://maps.googleapis.com/maps/api/place/details/json?place_id=1&key=[Filtered]')
        ->and($scrubbed->getSpans()[0]->getData()['url'])->toBe('https://maps.googleapis.com/x?key=[Filtered]');
});
