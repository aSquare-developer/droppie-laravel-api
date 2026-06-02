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
        Schema::table('routes', function (Blueprint $table) {
            $table->date('started_at')->nullable()->after('end_address');
        });

        DB::table('routes')->update([
            'started_at' => DB::raw('DATE(created_at)'),
        ]);

        Schema::table('routes', function (Blueprint $table) {
            $table->date('started_at')->nullable(false)->change();
            $table->dropColumn('comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('distance_km');
            $table->dropColumn('started_at');
        });
    }
};
