<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'vehicle_id',
    'period_from',
    'period_to',
    'odometer_start_km',
    'odometer_end_km',
    'total_distance_km',
    'profile_snapshot',
    'vehicle_snapshot',
    'rows',
    'generated_at',
])]
class TripReport extends Model
{
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'odometer_start_km' => 'float',
            'odometer_end_km' => 'float',
            'total_distance_km' => 'float',
            'profile_snapshot' => 'array',
            'vehicle_snapshot' => 'array',
            'rows' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Resolve {report} only among the authenticated user's reports, so another user's
     * report id returns 404.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        $user = request()->user();

        if (! $user) {
            return null;
        }

        return $user->tripReports()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
