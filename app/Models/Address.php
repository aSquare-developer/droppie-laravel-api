<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'place_id',
        'formatted_address',
        'postal_code',
        'city',
        'country',
        'country_code',
        'street',
        'street_number',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function startedRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'start_address_id');
    }

    public function endedRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'end_address_id');
    }
}
