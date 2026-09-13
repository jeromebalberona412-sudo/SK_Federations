<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('official_profiles')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE official_profiles MODIFY first_name VARCHAR(150) NOT NULL');
            DB::statement('ALTER TABLE official_profiles MODIFY last_name VARCHAR(150) NOT NULL');
            DB::statement('ALTER TABLE official_profiles MODIFY middle_name VARCHAR(150) NULL');

            return;
        }

        if ($driver === 'pgsql') {
            // Views depend on these columns; drop, alter, recreate.
            DB::statement('DROP VIEW IF EXISTS deleted_sk_officials CASCADE');
            DB::statement('DROP VIEW IF EXISTS deleted_sk_federations CASCADE');

            DB::statement('ALTER TABLE official_profiles ALTER COLUMN first_name TYPE VARCHAR(150)');
            DB::statement('ALTER TABLE official_profiles ALTER COLUMN last_name TYPE VARCHAR(150)');
            DB::statement('ALTER TABLE official_profiles ALTER COLUMN middle_name TYPE VARCHAR(150)');

            $this->recreateDeletedViews();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('official_profiles')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE official_profiles MODIFY first_name VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE official_profiles MODIFY last_name VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE official_profiles MODIFY middle_name VARCHAR(100) NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS deleted_sk_officials CASCADE');
            DB::statement('DROP VIEW IF EXISTS deleted_sk_federations CASCADE');

            DB::statement('ALTER TABLE official_profiles ALTER COLUMN first_name TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE official_profiles ALTER COLUMN last_name TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE official_profiles ALTER COLUMN middle_name TYPE VARCHAR(100)');

            $this->recreateDeletedViews();
        }
    }

    private function recreateDeletedViews(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'deleted_at')) {
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
};
