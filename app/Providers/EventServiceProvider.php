<?php

namespace App\Providers;

use App\Events\RouteCreated;
use App\Listeners\LogRouteCreated;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RouteCreated::class => [
            LogRouteCreated::class,
        ],
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
