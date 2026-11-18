<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['first_name', 'last_name', 'company_name', 'country', 'country_code', 'city', 'city_latitude', 'city_longitude'])]
class UserProfile extends Model
{
    protected function casts(): array
    {
        return [
            'city_latitude' => 'float',
            'city_longitude' => 'float',
        ];
    }

    /**
     * Preferences for address suggestions: prefer the profile country and places near the
     * profile city. They only rank results; they never hide addresses elsewhere.
     *
     * @return array{region?: string, location?: array{0: float, 1: float}}
     */
    public function addressSearchBias(): array
    {
        $bias = [];

        if (filled($this->country_code)) {
            $bias['region'] = strtolower($this->country_code);
        }

        if ($this->city_latitude !== null && $this->city_longitude !== null) {
            $bias['location'] = [$this->city_latitude, $this->city_longitude];
        }

        return $bias;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
