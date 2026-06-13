<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('country', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('registration_number', 50)->nullable();
            $table->string('make_model')->nullable();
            $table->decimal('odometer_km', 10, 1)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
        });

        DB::table('users')
            ->orderBy('id')
            ->each(function (object $user): void {
                DB::table('user_profiles')->insert([
                    'user_id' => $user->id,
                    'first_name' => $user->name,
                    'last_name' => $user->last_name,
                    'company_name' => $user->company_name,
                    'country' => $user->country,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]);

                $vehicleId = DB::table('vehicles')->insertGetId([
                    'user_id' => $user->id,
                    'registration_number' => $user->car_registration_number,
                    'make_model' => $user->car_make_model,
                    'odometer_km' => $user->car_mileage,
                    'is_active' => true,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]);

                DB::table('routes')
                    ->where('user_id', $user->id)
                    ->update(['vehicle_id' => $vehicleId]);
            });

        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable(false)->change();
        });

        Schema::create('trip_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('odometer_start_km', 10, 1);
            $table->decimal('odometer_end_km', 10, 1);
            $table->decimal('total_distance_km', 10, 1);
            $table->json('profile_snapshot');
            $table->json('vehicle_snapshot');
            $table->json('rows');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['vehicle_id', 'period_from', 'period_to']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'last_name',
                'company_name',
                'car_registration_number',
                'car_make_model',
                'car_mileage',
                'country',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('car_registration_number', 50)->nullable();
            $table->string('car_make_model')->nullable();
            $table->decimal('car_mileage', 10, 1)->nullable();
            $table->string('country', 100)->nullable();
        });

        DB::table('users')
            ->orderBy('id')
            ->each(function (object $user): void {
                $profile = DB::table('user_profiles')->where('user_id', $user->id)->first();
                $vehicle = DB::table('vehicles')
                    ->where('user_id', $user->id)
                    ->orderByDesc('is_active')
                    ->orderBy('id')
                    ->first();

                DB::table('users')->where('id', $user->id)->update([
                    'name' => $profile?->first_name ?? 'User',
                    'last_name' => $profile?->last_name,
                    'company_name' => $profile?->company_name,
                    'country' => $profile?->country,
                    'car_registration_number' => $vehicle?->registration_number,
                    'car_make_model' => $vehicle?->make_model,
                    'car_mileage' => $vehicle?->odometer_km,
                ]);
            });

        Schema::dropIfExists('trip_reports');

        Schema::table('routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
        });

        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('user_profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
