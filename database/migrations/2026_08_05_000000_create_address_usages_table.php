<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('use_count')->default(1);
            $table->timestamp('last_used_at');
            $table->timestamps();

            $table->unique(['user_id', 'address_id']);
            $table->index(['user_id', 'last_used_at']);
        });

        $startAddresses = DB::table('routes')->select([
            'user_id',
            DB::raw('start_address_id as address_id'),
            'updated_at',
        ]);
        $endAddresses = DB::table('routes')->select([
            'user_id',
            DB::raw('end_address_id as address_id'),
            'updated_at',
        ]);

        $routeAddresses = $startAddresses->unionAll($endAddresses);
        $addressUsages = DB::query()
            ->fromSub($routeAddresses, 'route_addresses')
            ->select(['user_id', 'address_id'])
            ->selectRaw('COUNT(*) as use_count')
            ->selectRaw('MAX(updated_at) as last_used_at')
            ->selectRaw('MIN(updated_at) as created_at')
            ->selectRaw('MAX(updated_at) as updated_at')
            ->groupBy('user_id', 'address_id');

        DB::table('address_usages')->insertUsing([
            'user_id',
            'address_id',
            'use_count',
            'last_used_at',
            'created_at',
            'updated_at',
        ], $addressUsages);
    }

    public function down(): void
    {
        Schema::dropIfExists('address_usages');
    }
};
