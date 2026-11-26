<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicateEmails = DB::table('users')
            ->selectRaw('LOWER(email)')
            ->groupByRaw('LOWER(email)')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateEmails) {
            throw new RuntimeException(
                'Cannot enforce case-insensitive user email uniqueness because duplicate email addresses already exist. Resolve the duplicates before retrying this migration.',
            );
        }

        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (LOWER(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
