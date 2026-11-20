<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

it('makes new accounts plain users', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::User)
        ->and($user->fresh()->role)->toBe(UserRole::User)
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->isModerator())->toBeFalse();
});

it('treats admins as moderators too', function () {
    expect(User::factory()->moderator()->make()->isModerator())->toBeTrue()
        ->and(User::factory()->moderator()->make()->isAdmin())->toBeFalse()
        ->and(User::factory()->admin()->make()->isModerator())->toBeTrue()
        ->and(User::factory()->admin()->make()->isAdmin())->toBeTrue();
});

it('lets only admins view the admin page and moderators and admins moderate', function () {
    $user = User::factory()->create();
    $moderator = User::factory()->moderator()->create();
    $admin = User::factory()->admin()->create();

    expect(Gate::forUser($user)->allows('viewAdmin'))->toBeFalse()
        ->and(Gate::forUser($moderator)->allows('viewAdmin'))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('viewAdmin'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('moderate'))->toBeFalse()
        ->and(Gate::forUser($moderator)->allows('moderate'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('moderate'))->toBeTrue();
});

it('keeps role out of mass assignment', function () {
    $user = new User(['email' => 'mari@example.com', 'password' => 'secret12', 'role' => 'admin']);

    expect($user->role)->toBe(UserRole::User);
});

it('ignores a role sent with web registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Mari',
        'email' => 'mari@example.com',
        'password' => 'droppie-2026',
        'role' => 'admin',
    ])->assertRedirect(route('dashboard'));

    expect(User::query()->sole()->role)->toBe(UserRole::User);
});

it('ignores a role sent with api registration', function () {
    $this->postJson('/api/register', [
        'name' => 'Mari',
        'email' => 'mari@example.com',
        'password' => 'droppie-2026',
        'role' => 'admin',
    ])->assertCreated()->assertJsonPath('user.role', 'user');

    expect(User::query()->sole()->role)->toBe(UserRole::User);
});

it('ignores a role in the google profile', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-user-1',
        'email' => 'mari@example.com',
        'email_verified' => true,
        'verified_email' => true,
        'name' => 'Mari Tamm',
        'role' => 'admin',
    ]));

    $this->get(route('oauth.google.callback'))->assertRedirect(route('dashboard'));

    expect(User::query()->sole()->role)->toBe(UserRole::User);
});

it('ignores a role sent with a web profile update', function () {
    $user = User::factory()->withProfile()->withVehicle()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Mari', 'role' => 'admin'])
        ->assertRedirect(route('account'));

    expect($user->fresh()->role)->toBe(UserRole::User);
});

it('ignores a role sent with an api profile update', function (string $method) {
    $user = User::factory()->withProfile()->withVehicle()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->json($method, '/api/profile', ['name' => 'Mari', 'role' => 'admin'])
        ->assertOk();

    expect($user->fresh()->role)->toBe(UserRole::User);
})->with(['PUT', 'PATCH']);

it('shows the role read-only in /api/me', function () {
    $admin = User::factory()->admin()->withProfile()->create();

    $this->withToken($admin->createToken('test')->plainTextToken)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.role', 'admin');
});
