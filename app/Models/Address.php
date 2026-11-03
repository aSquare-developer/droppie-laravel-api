<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Case-insensitive "contains" search over the given address columns.
     *
     * LIKE is case-sensitive on PostgreSQL but not on SQLite, so both sides are lowercased
     * and the term is escaped explicitly to behave the same in production and in tests.
     * "!" is the escape character because a backslash literal makes PDO's pgsql driver
     * misread the query and lose the "?" placeholder.
     *
     * @param  array<int, string>  $columns
     */
    public function scopeMatchingText(Builder $query, string $term, array $columns = [
        'formatted_address',
        'city',
        'postal_code',
    ]): Builder
    {
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
        $model = $query->getModel();
        $grammar = $model->getConnection()->getQueryGrammar();

        return $query->where(function (Builder $query) use ($columns, $like, $model, $grammar): void {
            foreach ($columns as $column) {
                $query->orWhereRaw(
                    'LOWER('.$grammar->wrap($model->getTable().'.'.$column).") LIKE ? ESCAPE '!'",
                    [$like]
                );
            }
        });
    }

    public function startedRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'start_address_id');
    }

    public function endedRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'end_address_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(AddressUsage::class);
    }
}
