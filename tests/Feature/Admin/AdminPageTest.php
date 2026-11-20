<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('answers 404 to guests, users and moderators', function (?string $role) {
    if ($role !== null) {
        $user = User::factory()->withProfile()->state(['role' => $role])->create();
        $this->actingAs($user);
    }

    $this->get('/admin')->assertNotFound();
})->with([
    'guest' => [null],
    'user' => ['user'],
    'moderator' => ['moderator'],
]);

it('shows the admin page to admins', function () {
    $admin = User::factory()->admin()->withProfile()->create(['email' => 'boss@example.com']);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Admins and moderators')
        ->assertSee('boss@example.com')
        ->assertSee('Google API');
});

it('shows the Admin menu link only to admins', function () {
    $user = User::factory()->withProfile()->create();
    $moderator = User::factory()->moderator()->withProfile()->create();
    $admin = User::factory()->admin()->withProfile()->create();

    $this->actingAs($user)->get(route('account'))->assertDontSee(route('admin'));
    $this->actingAs($moderator)->get(route('account'))->assertDontSee(route('admin'));
    $this->actingAs($admin)->get(route('account'))->assertSee(route('admin'));
});
