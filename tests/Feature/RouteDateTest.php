<?php

use App\Jobs\CalculateRouteDistance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('requires a trip date when creating a route', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/routes', [
            'start_address' => 'Helsinki',
            'end_address' => 'Espoo',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('started_at');
});

it('stores the trip date and omits comments from route responses', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/routes', [
            'start_address' => 'Helsinki',
            'end_address' => 'Espoo',
            'started_at' => '2026-06-01',
            'comment' => 'This should be ignored.',
        ])
        ->assertCreated();

    $data = $response->json('data');

    expect($data['started_at'])->toBe('2026-06-01');
    expect(array_key_exists('comment', $data))->toBeFalse();
    expect(Schema::hasColumn('routes', 'comment'))->toBeFalse();

    $this->assertDatabaseHas('routes', [
        'user_id' => $user->id,
        'start_address' => 'Helsinki',
        'end_address' => 'Espoo',
        'started_at' => '2026-06-01 00:00:00',
    ]);

    Queue::assertPushed(CalculateRouteDistance::class);
});
