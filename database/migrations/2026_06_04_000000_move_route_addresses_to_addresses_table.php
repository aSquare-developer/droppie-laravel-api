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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->nullable()->unique();
            $table->string('formatted_address');
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('street')->nullable();
            $table->string('street_number')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index('formatted_address');
            $table->index(['postal_code', 'city']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('start_address_id')->nullable()->after('user_id')->constrained('addresses')->restrictOnDelete();
            $table->foreignId('end_address_id')->nullable()->after('start_address_id')->constrained('addresses')->restrictOnDelete();
        });

        DB::table('routes')
            ->orderBy('id')
            ->chunkById(500, function ($routes): void {
                $routes->each(function (object $route): void {
                    DB::table('routes')
                        ->where('id', $route->id)
                        ->update([
                            'start_address_id' => $this->findOrCreateAddressId([
                                'place_id' => $route->start_place_id,
                                'formatted_address' => $route->start_address,
                                'postal_code' => $route->start_postal_code,
                                'city' => $route->start_city,
                                'country' => $route->start_country,
                                'country_code' => $route->start_country_code,
                                'street' => $route->start_street,
                                'street_number' => $route->start_street_number,
                                'latitude' => $route->start_latitude,
                                'longitude' => $route->start_longitude,
                            ]),
                            'end_address_id' => $this->findOrCreateAddressId([
                                'place_id' => $route->end_place_id,
                                'formatted_address' => $route->end_address,
                                'postal_code' => $route->end_postal_code,
                                'city' => $route->end_city,
                                'country' => $route->end_country,
                                'country_code' => $route->end_country_code,
                                'street' => $route->end_street,
                                'street_number' => $route->end_street_number,
                                'latitude' => $route->end_latitude,
                                'longitude' => $route->end_longitude,
                            ]),
                        ]);
                });
            });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropIndex(['start_place_id']);
            $table->dropIndex(['end_place_id']);

            $table->dropColumn([
                'start_address',
                'start_place_id',
                'start_postal_code',
                'start_city',
                'start_country',
                'start_country_code',
                'start_street',
                'start_street_number',
                'start_latitude',
                'start_longitude',
                'end_address',
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->string('start_address')->nullable();
            $table->string('start_place_id')->nullable()->index();
            $table->string('start_postal_code')->nullable();
            $table->string('start_city')->nullable();
            $table->string('start_country')->nullable();
            $table->string('start_country_code', 2)->nullable();
            $table->string('start_street')->nullable();
            $table->string('start_street_number')->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();

            $table->string('end_address')->nullable();
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

        DB::table('routes')
            ->leftJoin('addresses as start_addresses', 'routes.start_address_id', '=', 'start_addresses.id')
            ->leftJoin('addresses as end_addresses', 'routes.end_address_id', '=', 'end_addresses.id')
            ->select([
                'routes.id',
                'start_addresses.place_id as start_place_id',
                'start_addresses.formatted_address as start_address',
                'start_addresses.postal_code as start_postal_code',
                'start_addresses.city as start_city',
                'start_addresses.country as start_country',
                'start_addresses.country_code as start_country_code',
                'start_addresses.street as start_street',
                'start_addresses.street_number as start_street_number',
                'start_addresses.latitude as start_latitude',
                'start_addresses.longitude as start_longitude',
                'end_addresses.place_id as end_place_id',
                'end_addresses.formatted_address as end_address',
                'end_addresses.postal_code as end_postal_code',
                'end_addresses.city as end_city',
                'end_addresses.country as end_country',
                'end_addresses.country_code as end_country_code',
                'end_addresses.street as end_street',
                'end_addresses.street_number as end_street_number',
                'end_addresses.latitude as end_latitude',
                'end_addresses.longitude as end_longitude',
            ])
            ->orderBy('routes.id')
            ->get()
            ->each(function (object $route): void {
                DB::table('routes')
                    ->where('id', $route->id)
                    ->update([
                        'start_place_id' => $route->start_place_id,
                        'start_address' => $route->start_address,
                        'start_postal_code' => $route->start_postal_code,
                        'start_city' => $route->start_city,
                        'start_country' => $route->start_country,
                        'start_country_code' => $route->start_country_code,
                        'start_street' => $route->start_street,
                        'start_street_number' => $route->start_street_number,
                        'start_latitude' => $route->start_latitude,
                        'start_longitude' => $route->start_longitude,
                        'end_place_id' => $route->end_place_id,
                        'end_address' => $route->end_address,
                        'end_postal_code' => $route->end_postal_code,
                        'end_city' => $route->end_city,
                        'end_country' => $route->end_country,
                        'end_country_code' => $route->end_country_code,
                        'end_street' => $route->end_street,
                        'end_street_number' => $route->end_street_number,
                        'end_latitude' => $route->end_latitude,
                        'end_longitude' => $route->end_longitude,
                    ]);
            });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('start_address_id');
            $table->dropConstrainedForeignId('end_address_id');
        });

        Schema::dropIfExists('addresses');
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function findOrCreateAddressId(array $address): int
    {
        $placeId = $address['place_id'] ?: null;
        $formattedAddress = $address['formatted_address'] ?: 'Unknown address';

        $query = DB::table('addresses');

        if ($placeId) {
            $existingId = $query->where('place_id', $placeId)->value('id');
        } else {
            $existingId = $query
                ->whereNull('place_id')
                ->where('formatted_address', $formattedAddress)
                ->value('id');
        }

        if ($existingId) {
            return (int) $existingId;
        }

        return (int) DB::table('addresses')->insertGetId([
            'place_id' => $placeId,
            'formatted_address' => $formattedAddress,
            'postal_code' => $address['postal_code'] ?: null,
            'city' => $address['city'] ?: null,
            'country' => $address['country'] ?: null,
            'country_code' => $address['country_code'] ?: null,
            'street' => $address['street'] ?: null,
            'street_number' => $address['street_number'] ?: null,
            'latitude' => $address['latitude'] ?: null,
            'longitude' => $address['longitude'] ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
