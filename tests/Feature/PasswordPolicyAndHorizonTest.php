<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

it('applies the same password policy on web and api registration', function (string $password) {
    $payload = ['name' => 'Mari', 'email' => 'mari@example.com', 'password' => $password];

    $this->postJson('/api/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->post(route('register.store'), $payload)
        ->assertSessionHasErrors('password');

    expect(User::query()->count())->toBe(0);
})->with([
    'too short' => ['abc12345'],
    'no digits' => ['onlyletterspassword'],
    'no letters' => ['1234567890123'],
]);

it('accepts a password that follows the policy', function () {
    $this->postJson('/api/register', ['name' => 'Mari', 'email' => 'mari@example.com', 'password' => 'droppie-2026'])
        ->assertCreated();
});

it('lets existing users with older, shorter passwords sign in', function () {
    User::factory()->create(['email' => 'old@example.com', 'password' => 'secret']);

    $this->postJson('/api/login', ['email' => 'old@example.com', 'password' => 'secret'])->assertOk();
});

it('checks passwords against known breaches in production only', function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

    $rule = Password::defaults();
    expect(fn () => validator(['password' => 'droppie-2026'], ['password' => $rule])->validate())->not->toThrow(Exception::class);
    Http::assertNothingSent();

    app()->detectEnvironment(fn () => 'production');

    validator(['password' => 'droppie-2026'], ['password' => Password::defaults()])->passes();
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.pwnedpasswords.com/range/'));
});

it('opens the Horizon dashboard only for configured admins', function () {
    Config::set('horizon.admin_emails', ['admin@example.com']);
    $admin = User::factory()->create(['email' => 'Admin@Example.com']);
    $driver = User::factory()->create();

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($driver)->allows('viewHorizon'))->toBeFalse();
});

it('closes the Horizon dashboard when no admins are configured', function () {
    Config::set('horizon.admin_emails', []);

    expect(Gate::forUser(User::factory()->create())->allows('viewHorizon'))->toBeFalse();
});

it('reads Horizon admins from a comma-separated environment variable', function () {
    // Laravel reads $_SERVER first, then $_ENV and getenv(), so set all three and restore them.
    $name = 'HORIZON_ADMIN_EMAILS';
    $value = ' Admin@Example.com, ops@example.com ,';
    $previous = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];

    $_SERVER[$name] = $_ENV[$name] = $value;
    putenv("{$name}={$value}");

    try {
        $config = require config_path('horizon.php');
        expect($config['admin_emails'])->toBe(['admin@example.com', 'ops@example.com']);
    } finally {
        [$server, $env, $getenv] = $previous;

        if ($server === null) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $server;
        }

        if ($env === null) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $env;
        }

        putenv($getenv === false ? $name : "{$name}={$getenv}");
    }
});
