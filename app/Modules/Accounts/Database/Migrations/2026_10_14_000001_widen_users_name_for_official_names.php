<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'name')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // Three 150-character name parts, plus spaces and a short suffix.
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE users MODIFY name VARCHAR(500) NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            // Views select users.name, so Postgres refuses the type change until they are dropped.
            $this->dropViewIfPresent('deleted_sk_officials');
            $this->dropViewIfPresent('deleted_sk_federations');

            DB::statement('ALTER TABLE users ALTER COLUMN name TYPE VARCHAR(500)');

            $this->recreateDeletedViews();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'name')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE users MODIFY name VARCHAR(255) NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            $this->dropViewIfPresent('deleted_sk_officials');
            $this->dropViewIfPresent('deleted_sk_federations');

            DB::statement('ALTER TABLE users ALTER COLUMN name TYPE VARCHAR(255)');

            $this->recreateDeletedViews();
        }
    }

    private function recreateDeletedViews(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'deleted_at')) {
            return;
        }

        if (! $this->isBaseTable('deleted_sk_officials')) {
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
        }

        if ($this->isBaseTable('deleted_sk_federations')) {
            return;
        }

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

    private function dropViewIfPresent(string $name): void
    {
        DB::statement(
            "do $$ begin if exists (select 1 from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = current_schema() and c.relname = '{$name}' and c.relkind = 'v') then execute 'drop view {$name} cascade'; end if; end $$;"
        );
    }

    private function isBaseTable(string $name): bool
    {
        $row = DB::selectOne(
            'select c.relkind from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = current_schema() and c.relname = ?',
            [$name]
        );

        return $row !== null && $row->relkind === 'r';
    }
};
