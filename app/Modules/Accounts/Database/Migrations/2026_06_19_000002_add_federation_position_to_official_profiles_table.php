<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('official_profiles', 'federation_position')) {
                $table->string('federation_position', 255)->nullable()->after('position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('official_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('official_profiles', 'federation_position')) {
                $table->dropColumn('federation_position');
            }
        });
    }
};
