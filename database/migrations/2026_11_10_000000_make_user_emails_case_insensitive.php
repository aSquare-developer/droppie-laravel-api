<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Lowercase and trim stored emails.
     *
     * The unique email index already compares case-insensitively through the utf8mb4 *_ci
     * collation, so no extra index is needed. The binary comparison finds emails that differ
     * from their normalized form only in letter case; an email whose normalized form would
     * clash with another account is left untouched for a manual decision.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE users SET email = LOWER(TRIM(email))
            WHERE email COLLATE utf8mb4_bin <> LOWER(TRIM(email))
              AND NOT EXISTS (
                  SELECT 1 FROM (SELECT id, email FROM users) other
                  WHERE other.id <> users.id AND LOWER(TRIM(other.email)) = LOWER(TRIM(users.email))
              )
            SQL);
    }

    public function down(): void
    {
        //
    }
};
