<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('official_profiles', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('suffix');
            }

            if (! Schema::hasColumn('official_profiles', 'age')) {
                $table->unsignedTinyInteger('age')->nullable()->after('date_of_birth');
            }

            if (! Schema::hasColumn('official_profiles', 'contact_number')) {
                $table->string('contact_number', 20)->nullable()->after('age');
            }
        });
    }

    public function down(): void
    {
        Schema::table('official_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('official_profiles', 'contact_number')) {
                $table->dropColumn('contact_number');
            }

            if (Schema::hasColumn('official_profiles', 'age')) {
                $table->dropColumn('age');
            }

            if (Schema::hasColumn('official_profiles', 'date_of_birth')) {
                $table->dropColumn('date_of_birth');
            }
        });
    }
};
