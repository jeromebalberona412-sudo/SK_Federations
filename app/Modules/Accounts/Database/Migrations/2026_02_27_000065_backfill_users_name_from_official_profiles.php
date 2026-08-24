<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        $middleTokenSql = 'NULL';

        if (Schema::hasColumn('official_profiles', 'middle_name')) {
            $middleTokenSql = "NULLIF(CONCAT(UPPER(LEFT(TRIM(COALESCE(official_profiles.middle_name, '')), 1)), '.'), '.')";
        } elseif (Schema::hasColumn('official_profiles', 'middle_initial')) {
            $middleTokenSql = "NULLIF(official_profiles.middle_initial, '')";
        }

        if ($driver === 'pgsql') {
            DB::statement(<<<SQL
                UPDATE users
                SET name = TRIM(CONCAT_WS(
                    ' ',
                    official_profiles.first_name,
                    {$middleTokenSql},
                    official_profiles.last_name,
                    NULLIF(official_profiles.suffix, '')
                ))
                FROM official_profiles
                WHERE official_profiles.user_id = users.id
            SQL);

            return;
        }

        DB::statement(<<<SQL
            UPDATE users
            INNER JOIN official_profiles ON official_profiles.user_id = users.id
            SET users.name = TRIM(CONCAT_WS(
                ' ',
                official_profiles.first_name,
                {$middleTokenSql},
                official_profiles.last_name,
                NULLIF(official_profiles.suffix, '')
            ))
        SQL);
    }

    public function down(): void
    {
        // One-way data backfill; no rollback.
    }
};
