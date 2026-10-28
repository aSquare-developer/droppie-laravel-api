<?php

namespace App\Models;

use App\Enums\DistanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Route extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Route $route): void {
            if ($route->vehicle_id || ! $route->user_id) {
                return;
            }

            $route->vehicle_id = User::query()
                ->find($route->user_id)
                ?->activeVehicle()
                ->value('id');

            if (! $route->vehicle_id) {
                throw new LogicException('An active vehicle is required to create a route.');
            }
        });
    }

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'start_address_id',
        'end_address_id',
        'started_at',
        'distance_km',
        'distance_status',
        'distance_error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'distance_status' => DistanceStatus::class,
        ];
    }

    /**
     * Resolve {route} only among the authenticated user's routes, so another user's route id
     * returns 404 instead of revealing that it exists with a 403.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        $user = request()->user();

        if (! $user) {
            return null;
        }

        return $user->routes()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    public function isDistanceCalculationInProgress(): bool
    {
        return $this->distance_status?->isInProgress() ?? false;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function startAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'start_address_id');
    }

    public function endAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'end_address_id');
    }
}
