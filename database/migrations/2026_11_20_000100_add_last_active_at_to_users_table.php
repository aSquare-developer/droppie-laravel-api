<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_active_at')->nullable()->index()->after('role');
        });

        // Start from the latest activity already on record: web sessions, API token use and
        // the newest route. Users with none of these stay null until their next request.
        $latest = [];

        $remember = function ($rows, callable $toTimestamp) use (&$latest): void {
            foreach ($rows as $userId => $value) {
                if ($value === null) {
                    continue;
                }

                $timestamp = $toTimestamp($value);
                $latest[$userId] = max($latest[$userId] ?? 0, $timestamp);
            }
        };

        if (Schema::hasTable('sessions')) {
            $remember(
                DB::table('sessions')->whereNotNull('user_id')->groupBy('user_id')->pluck(DB::raw('MAX(last_activity)'), 'user_id'),
                fn ($value): int => (int) $value,
            );
        }

        $remember(
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->groupBy('tokenable_id')
                ->pluck(DB::raw('MAX(last_used_at)'), 'tokenable_id'),
            fn ($value): int => Carbon::parse($value)->getTimestamp(),
        );

        $remember(
            DB::table('routes')->groupBy('user_id')->pluck(DB::raw('MAX(created_at)'), 'user_id'),
            fn ($value): int => Carbon::parse($value)->getTimestamp(),
        );

        foreach ($latest as $userId => $timestamp) {
            if ($timestamp > 0) {
                DB::table('users')->where('id', $userId)->update([
                    'last_active_at' => Carbon::createFromTimestamp($timestamp)->toDateTimeString(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_active_at']);
            $table->dropColumn('last_active_at');
        });
    }
};
