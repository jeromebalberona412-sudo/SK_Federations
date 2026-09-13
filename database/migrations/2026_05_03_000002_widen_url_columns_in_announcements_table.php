<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        if (Schema::hasColumn('announcements', 'image_url')) {
            DB::statement('ALTER TABLE announcements ALTER COLUMN image_url TYPE TEXT');
        }

        if (Schema::hasColumn('announcements', 'link_url')) {
            DB::statement('ALTER TABLE announcements ALTER COLUMN link_url TYPE TEXT');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        if (Schema::hasColumn('announcements', 'image_url')) {
            DB::statement('ALTER TABLE announcements ALTER COLUMN image_url TYPE VARCHAR(255)');
        }

        if (Schema::hasColumn('announcements', 'link_url')) {
            DB::statement('ALTER TABLE announcements ALTER COLUMN link_url TYPE VARCHAR(255)');
        }
    }
};
