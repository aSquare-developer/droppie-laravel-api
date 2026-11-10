<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const INDEX = 'users_email_lower_unique';

    /**
     * Lowercase stored emails and enforce case-insensitive uniqueness.
     *
     * Accounts whose emails differ only in letter case are left untouched: merging them is a
     * manual decision. While such duplicates exist the index is not created (a warning is
     * logged instead), and the application-level check still blocks new duplicates.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE users SET email = LOWER(TRIM(email))
            WHERE email <> LOWER(TRIM(email))
              AND NOT EXISTS (
                  SELECT 1 FROM users other
                  WHERE other.id <> users.id AND LOWER(TRIM(other.email)) = LOWER(TRIM(users.email))
              )
            SQL);

        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        $duplicates = DB::table('users')
            ->selectRaw('LOWER(email) AS email, COUNT(*) AS accounts')
            ->groupByRaw('LOWER(email)')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            Log::warning('Skipped the case-insensitive unique email index: some emails differ only in letter case.', [
                'emails' => $duplicates->pluck('email')->all(),
            ]);

            return;
        }

        DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON users (LOWER(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
