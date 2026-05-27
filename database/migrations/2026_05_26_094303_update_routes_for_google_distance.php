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
            $table->integer('distance_km')->nullable()->change();
            $table->string('distance_status')->default('pending');
            $table->text('distance_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->integer('distance_km')->nullable(false)->change();
            $table->dropColumn('distance_status');
            $table->dropColumn('distance_error');
        });
    }
};
