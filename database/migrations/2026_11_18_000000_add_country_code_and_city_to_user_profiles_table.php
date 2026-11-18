<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Country becomes a code from a list and the profile gets a home city. Both steer the
     * address suggestions towards the user's area.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('country');
            $table->string('city', 100)->nullable()->after('country_code');
            $table->decimal('city_latitude', 10, 7)->nullable()->after('city');
            $table->decimal('city_longitude', 10, 7)->nullable()->after('city_latitude');
        });

        // Map the old free-text country to a code where the name matches the list.
        $codes = collect(config('countries'))->mapWithKeys(
            fn (string $name, string $code): array => [Str::lower($name) => $code]
        );

        DB::table('user_profiles')->whereNotNull('country')->orderBy('id')->each(function (object $profile) use ($codes): void {
            $name = Str::lower(trim($profile->country));
            $code = $codes->get($name) ?? (strlen($name) === 2 && config('countries.'.strtoupper($name)) ? strtoupper($name) : null);

            if ($code !== null) {
                DB::table('user_profiles')->where('id', $profile->id)->update([
                    'country_code' => $code,
                    'country' => config('countries.'.$code),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'city', 'city_latitude', 'city_longitude']);
        });
    }
};
