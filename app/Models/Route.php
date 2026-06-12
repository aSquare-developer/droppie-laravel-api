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
        ];
    }

    public function isDistanceCalculationInProgress(): bool
    {
        return in_array($this->distance_status, ['pending', 'processing'], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
