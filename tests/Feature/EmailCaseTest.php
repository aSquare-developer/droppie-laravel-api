<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('stores emails lowercased and trimmed', function () {
    $this->postJson('/api/register', [
        'name' => 'Mari',
        'email' => '  Mari@Example.COM ',
        'password' => 'droppie-2026',
    ])->assertCreated();

    expect(User::query()->sole()->email)->toBe('mari@example.com');
});

it('rejects registering the same email in another letter case', function (string $route, bool $json) {
    User::factory()->create(['email' => 'mari@example.com']);

    $payload = ['name' => 'Mari', 'email' => 'MARI@example.com', 'password' => 'droppie-2026'];

    if ($json) {
        $this->postJson($route, $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
    } else {
        $this->post($route, $payload)->assertSessionHasErrors('email');
    }

    expect(User::query()->count())->toBe(1);
})->with([
    'api' => ['/api/register', true],
    'web' => ['/register', false],
]);

it('logs in through the api regardless of email letter case', function () {
    User::factory()->create(['email' => 'mari@example.com', 'password' => 'secret12']);

    $this->postJson('/api/login', ['email' => 'Mari@Example.com', 'password' => 'secret12'])
        ->assertOk()
        ->assertJsonStructure(['token']);
});

it('logs in on the web regardless of email letter case', function () {
    $user = User::factory()->create(['email' => 'mari@example.com', 'password' => 'secret12']);

    $this->post(route('login.store'), ['email' => 'MARI@EXAMPLE.COM', 'password' => 'secret12'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('still signs in legacy accounts stored with uppercase letters', function () {
    $user = User::factory()->create(['password' => 'secret12']);
    DB::table('users')->where('id', $user->id)->update(['email' => 'Legacy@Example.com']);

    $this->postJson('/api/login', ['email' => 'legacy@example.com', 'password' => 'secret12'])->assertOk();
});

it('rejects a second account in another letter case at the database level', function () {
    User::factory()->create(['email' => 'mari@example.com']);

    expect(fn () => DB::table('users')->insert([
        'email' => 'MARI@example.com',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
