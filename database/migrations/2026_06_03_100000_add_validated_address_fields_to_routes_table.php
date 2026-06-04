<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->string('start_place_id')->nullable()->index();
            $table->string('start_postal_code')->nullable();
            $table->string('start_city')->nullable();
            $table->string('start_country')->nullable();
            $table->string('start_country_code', 2)->nullable();
            $table->string('start_street')->nullable();
            $table->string('start_street_number')->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();

            $table->string('end_place_id')->nullable()->index();
            $table->string('end_postal_code')->nullable();
            $table->string('end_city')->nullable();
            $table->string('end_country')->nullable();
            $table->string('end_country_code', 2)->nullable();
            $table->string('end_street')->nullable();
            $table->string('end_street_number')->nullable();
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn([
                'start_place_id',
                'start_postal_code',
                'start_city',
                'start_country',
                'start_country_code',
                'start_street',
                'start_street_number',
                'start_latitude',
                'start_longitude',
                'end_place_id',
                'end_postal_code',
                'end_city',
                'end_country',
                'end_country_code',
                'end_street',
                'end_street_number',
                'end_latitude',
                'end_longitude',
            ]);
        });
    }
};
