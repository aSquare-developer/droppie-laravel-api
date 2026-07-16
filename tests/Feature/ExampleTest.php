<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests from the root page to login', function () {
    $this->get('/')
        ->assertRedirect(route('login'));
});

it('redirects authenticated users from the root page to the dashboard', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});
