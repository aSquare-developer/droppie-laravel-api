<?php

use App\Models\Address;
use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * When distances finish, the dashboard script fetches the page again and swaps only the
 * data-live-region parts, so the trip form keeps what the user typed.
 */
function liveRegion(string $html, string $name): string
{
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $node = (new DOMXPath($document))->query("//*[@data-live-region='{$name}']")->item(0);

    expect($node)->not->toBeNull();

    return $document->saveHTML($node);
}

it('marks the parts that depend on route distances as live regions, but not the trip form', function () {
    $user = User::factory()->create();
    Route::factory()->create(['user_id' => $user->id, 'started_at' => '2026-02-08', 'distance_status' => 'completed']);

    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    foreach (['metrics', 'day-summary', 'routes'] as $region) {
        expect(liveRegion($html, $region))->not->toContain('data-route-form');
    }

    expect(liveRegion($html, 'routes'))->toContain('data-route-card');
});

it('renders the finished distance, totals and unlocked actions on the next page load', function () {
    $user = User::factory()->create();
    $route = Route::factory()->create([
        'user_id' => $user->id,
        'started_at' => '2026-02-08',
        'distance_km' => null,
        'distance_status' => 'pending',
        'start_address_id' => Address::factory()->create(['formatted_address' => 'Raatuse tn 18, Tartu'])->id,
    ]);

    $pending = $this->actingAs($user)->get(route('dashboard'))->getContent();

    expect(liveRegion($pending, 'routes'))->toContain('data-route-active="true"')
        ->and(liveRegion($pending, 'metrics'))->toMatch('/In progress<\/dt>\s*<dd>1<\/dd>/');

    $route->update(['distance_km' => 12.4, 'distance_status' => 'completed']);

    $finished = $this->actingAs($user)->get(route('dashboard'))->getContent();

    expect(liveRegion($finished, 'routes'))
        ->toContain('data-route-active="false"')
        ->toContain('12.4 km')
        ->toContain(route('dashboard', ['edit' => $route->id]))
        ->and(liveRegion($finished, 'metrics'))->toMatch('/In progress<\/dt>\s*<dd>0<\/dd>/')
        ->and(liveRegion($finished, 'day-summary'))->toContain('12.4 km');
});
