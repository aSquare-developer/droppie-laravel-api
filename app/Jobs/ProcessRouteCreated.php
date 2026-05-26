<?php

namespace App\Jobs;

use App\Models\Route;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessRouteCreated implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(Public Route $route)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing route', [
            'route_id' => $this->route->id,
            'user_id' => $this->route->user_id,
        ]);
        // throw new \Exception('Something went wrong'); // For testing purposes, we throw an exception to trigger the retry mechanism
    }
}
