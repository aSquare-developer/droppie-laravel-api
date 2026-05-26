<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

use App\Events\RouteCreated;
use Illuminate\Support\Facades\Log;

class LogRouteCreated implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(RouteCreated $event): void
    {
        Log::info('Route created: ', [
            'route_id' => $event->route->id,
            'user_id' => $event->route->user_id,
        ]);
    }
}
