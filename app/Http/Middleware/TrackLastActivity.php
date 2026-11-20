<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records when a signed-in user was last active, at most once every five minutes.
 */
class TrackLastActivity
{
    public const INTERVAL_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Runs after the route's auth middleware, so the guard that authenticated the request
        // (session or Sanctum) is the default one. hasUser() avoids resolving a user on
        // public pages.
        if (Auth::hasUser() && ($user = Auth::user()) instanceof User) {
            $this->touch($user);
        }

        return $response;
    }

    private function touch(User $user): void
    {
        $now = now();

        if ($user->last_active_at !== null && $user->last_active_at->gt($now->copy()->subMinutes(self::INTERVAL_MINUTES))) {
            return;
        }

        // A plain query: no model events and no updated_at change.
        $user->newQuery()->whereKey($user->getKey())->toBase()->update(['last_active_at' => $now]);

        $user->last_active_at = $now;
        $user->syncOriginalAttribute('last_active_at');
    }
}
