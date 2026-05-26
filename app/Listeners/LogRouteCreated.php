<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

use App\Events\RouteCreated;
use Illuminate\Support\Facades\Log;

use App\Jobs\ProcessRouteCreated;

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
