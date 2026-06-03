<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_address',
        'start_place_id',
        'start_postal_code',
        'start_city',
        'start_country',
        'start_country_code',
        'start_street',
        'start_street_number',
        'start_latitude',
        'start_longitude',
        'end_address',
        'end_place_id',
        'end_postal_code',
        'end_city',
        'end_country',
        'end_country_code',
        'end_street',
        'end_street_number',
        'end_latitude',
        'end_longitude',
        'started_at',
        'distance_km',
        'distance_status',
        'distance_error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'start_latitude' => 'float',
            'start_longitude' => 'float',
            'end_latitude' => 'float',
            'end_longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
