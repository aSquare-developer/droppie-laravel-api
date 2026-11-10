<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The profile mileage is again the start reading of the next report, so the baseline date
     * added in 2026_11_10_100000 is no longer used.
     */
    public function up(): void
    {
        if (Schema::hasColumn('vehicles', 'odometer_recorded_on')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('odometer_recorded_on');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('odometer_recorded_on')->nullable()->after('odometer_km');
        });
    }
};
