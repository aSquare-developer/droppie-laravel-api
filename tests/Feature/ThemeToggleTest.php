<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the theme toggle in the top bar of every workspace page', function (string $page) {
    $this->actingAs(User::factory()->create())
        ->get(route($page))
        ->assertOk()
        ->assertSee('data-theme-toggle', false)
        ->assertSee('Theme: follow system');
})->with(['dashboard', 'reports', 'account']);

it('applies a stored theme before the page paints on every page', function (string $url, bool $auth) {
    $request = $auth ? $this->actingAs(User::factory()->create()) : $this;

    $request->get($url)
        ->assertOk()
        ->assertSee('<meta name="color-scheme" content="light dark">', false)
        ->assertSeeInOrder(['<head>', "localStorage.getItem('droppie-theme')", '</head>', '<body>'], false);
})->with([
    'login' => ['/login', false],
    'dashboard' => ['/dashboard', true],
]);
