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
            DB::statement('ALTER TABLE official_profiles ALTER COLUMN first_name TYPE VARCHAR(150)');
            DB::statement('ALTER TABLE official_profiles ALTER COLUMN last_name TYPE VARCHAR(150)');
            DB::statement('ALTER TABLE official_profiles ALTER COLUMN middle_name TYPE VARCHAR(150)');
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
            DB::statement('ALTER TABLE official_profiles ALTER COLUMN first_name TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE official_profiles ALTER COLUMN last_name TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE official_profiles ALTER COLUMN middle_name TYPE VARCHAR(100)');
        }
    }
};
