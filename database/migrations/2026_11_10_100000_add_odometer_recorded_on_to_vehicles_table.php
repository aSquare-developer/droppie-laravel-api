<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The vehicle mileage becomes a baseline reading with a date instead of a counter that
     * reports advance (#35).
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('odometer_recorded_on')->nullable()->after('odometer_km');
        });

        // Until now a report set the mileage to its ending reading. If the stored mileage still
        // equals the ending reading of the last report that moved it, it is the reading at the end
        // of that report's period. Otherwise it was entered in the profile, last on updated_at.
        DB::table('vehicles')->whereNotNull('odometer_km')->orderBy('id')->each(function (object $vehicle): void {
            $lastReport = DB::table('trip_reports')
                ->where('vehicle_id', $vehicle->id)
                ->where('total_distance_km', '>', 0)
                ->orderByDesc('generated_at')
                ->orderByDesc('id')
                ->first();

            $recordedOn = $lastReport !== null && (float) $lastReport->odometer_end_km === (float) $vehicle->odometer_km
                ? $lastReport->period_to
                : $vehicle->updated_at;

            DB::table('vehicles')->where('id', $vehicle->id)->update([
                'odometer_recorded_on' => Carbon::parse($recordedOn ?? now())->toDateString(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('odometer_recorded_on');
        });
    }
};
