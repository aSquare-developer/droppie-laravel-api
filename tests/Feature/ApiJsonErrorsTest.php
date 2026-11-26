<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * API clients get JSON errors even when they forget the Accept header, instead of a redirect
 * or an HTML error page.
 */
function apiToken(): string
{
    return User::factory()->withProfile()->withVehicle()->create()->createToken('test')->plainTextToken;
}

it('answers validation errors with 422 json without an accept header', function () {
    $this->withToken(apiToken())
        ->post('/api/routes', [])
        ->assertUnprocessable()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonStructure(['message', 'errors']);
});

it('answers a missing record with 404 json without an accept header', function () {
    $this->withToken(apiToken())
        ->get('/api/routes/999999')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/json');
});

it('answers unauthenticated api requests with 401 json instead of redirecting to the login page', function () {
    $this->get('/api/me')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/json')
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('keeps redirecting web form errors back to the form', function () {
    $this->from(route('login'))
        ->post(route('login.store'), [])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});
