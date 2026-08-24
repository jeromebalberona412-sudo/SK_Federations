<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('official_profiles', 'middle_name')) {
            Schema::table('official_profiles', function (Blueprint $table) {
                $table->string('middle_name', 100)->nullable()->after('last_name');
            });
        }

        if (Schema::hasColumn('official_profiles', 'middle_initial')) {
            DB::statement("UPDATE official_profiles SET middle_name = TRIM(BOTH '.' FROM middle_initial) WHERE middle_name IS NULL AND middle_initial IS NOT NULL");

            Schema::table('official_profiles', function (Blueprint $table) {
                $table->dropColumn('middle_initial');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('official_profiles', 'middle_initial')) {
            Schema::table('official_profiles', function (Blueprint $table) {
                $table->string('middle_initial', 5)->nullable()->after('middle_name');
            });
        }

        if (Schema::hasColumn('official_profiles', 'middle_name')) {
            DB::statement("UPDATE official_profiles SET middle_initial = CONCAT(UPPER(LEFT(TRIM(middle_name), 1)), '.') WHERE middle_name IS NOT NULL");

            Schema::table('official_profiles', function (Blueprint $table) {
                $table->dropColumn('middle_name');
            });
        }
    }
};
