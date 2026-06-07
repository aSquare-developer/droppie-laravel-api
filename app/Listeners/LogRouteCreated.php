<?php

namespace App\Listeners;

use App\Events\RouteCreated;
use App\Jobs\ProcessRouteCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        ProcessRouteCreated::dispatch($event->route);
    }
}
