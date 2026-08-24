<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE official_terms target
            SET status = 'INACTIVE'
            FROM (
                SELECT id
                FROM (
                    SELECT id,
                           ROW_NUMBER() OVER (
                               PARTITION BY official_profile_id
                               ORDER BY term_end DESC, id DESC
                           ) AS row_number
                    FROM official_terms
                    WHERE status = 'ACTIVE'
                ) ranked
                WHERE ranked.row_number > 1
            ) duplicates
            WHERE target.id = duplicates.id
        SQL);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX official_terms_one_active_per_profile_idx ON official_terms (official_profile_id) WHERE status = 'ACTIVE'"
            );

            return;
        }

        Schema::table('official_terms', function (Blueprint $table) {
            $table->unique(['official_profile_id', 'status'], 'official_terms_profile_status_unique');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS official_terms_one_active_per_profile_idx');

            return;
        }

        Schema::table('official_terms', function (Blueprint $table) {
            $table->dropUnique('official_terms_profile_status_unique');
        });
    }
};
