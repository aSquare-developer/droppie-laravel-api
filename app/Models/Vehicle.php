<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['registration_number', 'make_model', 'odometer_km', 'is_active'])]
class Vehicle extends Model
{
    protected function casts(): array
    {
        return [
            'odometer_km' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    public function tripReports(): HasMany
    {
        return $this->hasMany(TripReport::class);
    }
}
