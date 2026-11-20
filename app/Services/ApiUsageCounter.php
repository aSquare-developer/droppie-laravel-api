<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Counts outgoing Google API requests per service and day in api_usage_daily.
 */
class ApiUsageCounter
{
    public const ROUTES = 'routes';

    public const ROUTES_CACHE_HIT = 'routes_cache_hit';

    public const PLACES_AUTOCOMPLETE = 'places_autocomplete';

    public const PLACES_DETAILS = 'places_details';

    public const PLACES_FIND_PLACE = 'places_find_place';

    public const ADDRESS_VALIDATION = 'address_validation';

    public function record(string $service): void
    {
        try {
            DB::table('api_usage_daily')->upsert(
                [['date' => now()->toDateString(), 'service' => $service, 'requests' => 1]],
                ['date', 'service'],
                ['requests' => DB::raw('requests + 1')],
            );
        } catch (Throwable $exception) {
            // Counting is bookkeeping only; it must never break the request being counted.
            Log::warning('Could not record API usage.', [
                'service' => $service,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
