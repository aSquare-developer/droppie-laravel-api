<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Route extends Model
{

    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_address',
        'end_address',
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

    public function user(): BelongsTo 
    {
        return $this->belongsTo(User::class);
    }
}
