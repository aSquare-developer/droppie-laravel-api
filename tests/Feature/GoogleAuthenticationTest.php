<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

it('redirects guests to google for authentication', function () {
    Socialite::fake('google');

    $this->get(route('oauth.google.redirect'))
        ->assertRedirect('https://socialite.fake/google/authorize');
});

it('creates the droppie account structure after google authentication', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-user-123',
        'email' => 'mari@example.com',
        'email_verified' => true,
        'verified_email' => true,
        'name' => 'Mari Tamm',
        'given_name' => 'Mari',
        'family_name' => 'Tamm',
    ]));

    $this->get(route('oauth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'mari@example.com',
    ]);

    $user = User::query()->where('email', 'mari@example.com')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull();
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-user-123',
    ]);
    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'first_name' => 'Mari',
        'last_name' => 'Tamm',
    ]);
    $this->assertDatabaseHas('vehicles', [
        'user_id' => $user->id,
        'is_active' => true,
    ]);
});

it('links google to an existing user with the same verified email', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'existing@example.com',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-existing-123',
        'email' => 'existing@example.com',
        'email_verified' => true,
        'verified_email' => true,
        'name' => 'Existing User',
    ]));

    $this->get(route('oauth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1);
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-existing-123',
    ]);
});

it('rejects a google account with an unverified email', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-unverified-123',
        'email' => 'unverified@example.com',
        'email_verified' => false,
        'verified_email' => false,
    ]));

    $this->get(route('oauth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('oauth');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'unverified@example.com']);
});
