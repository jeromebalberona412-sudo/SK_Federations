<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        if (! Schema::hasColumn('announcements', 'is_federation_wide')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->boolean('is_federation_wide')->default(false)->after('barangay_id');
            });
        }

        // Make barangay_id nullable using raw SQL (avoids FK constraint issues on PostgreSQL)
        DB::statement('ALTER TABLE announcements ALTER COLUMN barangay_id DROP NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('announcements') || ! Schema::hasColumn('announcements', 'is_federation_wide')) {
            return;
        }
        // Only restore NOT NULL if no federation-wide rows exist
        DB::statement('UPDATE announcements SET barangay_id = 0 WHERE barangay_id IS NULL');
        DB::statement('ALTER TABLE announcements ALTER COLUMN barangay_id SET NOT NULL');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('is_federation_wide');
        });
    }
};
