<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE announcements ALTER COLUMN image_url TYPE TEXT');
        DB::statement('ALTER TABLE announcements ALTER COLUMN link_url TYPE TEXT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE announcements ALTER COLUMN image_url TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE announcements ALTER COLUMN link_url TYPE VARCHAR(255)');
    }
};
