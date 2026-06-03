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
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('car_registration_number', 50)->nullable();
            $table->string('car_make_model')->nullable();
            $table->unsignedInteger('car_mileage')->nullable();
            $table->string('country', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_name',
                'company_name',
                'car_registration_number',
                'car_make_model',
                'car_mileage',
                'country',
            ]);
        });
    }
};
