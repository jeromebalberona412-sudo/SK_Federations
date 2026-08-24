<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('users')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->index(['role', 'deleted_at'], 'users_role_deleted_at_index');
                });
            } catch (\Throwable) {
                // Index may already exist.
            }
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW deleted_sk_officials AS
            SELECT
                u.id,
                u.tenant_id,
                u.barangay_id,
                u.name,
                u.email,
                u.status,
                u.email_verified_at,
                u.deleted_at,
                op.first_name,
                op.middle_name,
                op.last_name,
                op.suffix,
                op.sex,
                op.date_of_birth,
                op.age,
                op.contact_number,
                op.position,
                op.municipality,
                op.province,
                op.region
            FROM users u
            LEFT JOIN official_profiles op ON op.user_id = u.id
            WHERE u.deleted_at IS NOT NULL
              AND u.role = 'sk_official'
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW deleted_sk_federations AS
            SELECT
                u.id,
                u.tenant_id,
                u.barangay_id,
                u.name,
                u.email,
                u.status,
                u.email_verified_at,
                u.deleted_at,
                op.first_name,
                op.middle_name,
                op.last_name,
                op.suffix,
                op.sex,
                op.date_of_birth,
                op.age,
                op.contact_number,
                op.position,
                op.municipality,
                op.province,
                op.region
            FROM users u
            LEFT JOIN official_profiles op ON op.user_id = u.id
            WHERE u.deleted_at IS NOT NULL
              AND u.role = 'sk_fed'
        SQL);
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS deleted_sk_officials');
            DB::statement('DROP VIEW IF EXISTS deleted_sk_federations');
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_role_deleted_at_index');
            });
        }
    }
};
