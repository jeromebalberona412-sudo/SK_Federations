<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('official_profiles', 'sex')) {
                $table->string('sex', 10)->nullable()->after('suffix');
            }
        });
    }

    public function down(): void
    {
        Schema::table('official_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('official_profiles', 'sex')) {
                $table->dropColumn('sex');
            }
        });
    }
};
