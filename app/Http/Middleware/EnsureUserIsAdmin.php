<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin pages answer 404 to everyone else, guests included, so they do not reveal that
 * they exist (a 403 or a login redirect would).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() !== null && Gate::forUser($request->user())->allows('viewAdmin'), 404);

        return $next($request);
    }
}
