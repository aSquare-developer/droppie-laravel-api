<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
