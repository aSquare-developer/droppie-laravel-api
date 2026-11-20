<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('service', 50);
            $table->unsignedInteger('requests')->default(0);

            $table->unique(['date', 'service']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_daily');
    }
};
