<?php

namespace App\Models;

use App\Enums\DistanceStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['registration_number', 'make_model', 'odometer_km', 'odometer_recorded_on', 'is_active'])]
class Vehicle extends Model
{
    protected function casts(): array
    {
        return [
            'odometer_km' => 'float',
            'odometer_recorded_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Odometer reading at the end of the given day.
     *
     * `odometer_km` is a baseline: the reading at the end of `odometer_recorded_on` (today when
     * no date is stored). Completed trips of this vehicle after that day are added; for an
     * earlier day the trips between it and the baseline are subtracted. The result does not
     * depend on which reports were generated before.
     */
    public function odometerAt(CarbonInterface|string $day): ?float
    {
        if ($this->odometer_km === null) {
            return null;
        }

        $day = Carbon::parse($day)->toDateString();
        $baseline = ($this->odometer_recorded_on ?? today())->toDateString();

        [$after, $until, $sign] = $day >= $baseline ? [$baseline, $day, 1] : [$day, $baseline, -1];

        $distance = (float) $this->routes()
            ->where('distance_status', DistanceStatus::Completed)
            ->whereDate('started_at', '>', $after)
            ->whereDate('started_at', '<=', $until)
            ->sum('distance_km');

        return round($this->odometer_km + $sign * $distance, 1);
    }

    public function currentOdometer(): ?float
    {
        return $this->odometerAt(today());
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
