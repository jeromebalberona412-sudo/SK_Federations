<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE community_feeds DROP CONSTRAINT IF EXISTS announcements_type_check');
        DB::statement('ALTER TABLE community_feeds DROP CONSTRAINT IF EXISTS community_feeds_type_check');

        DB::statement("ALTER TABLE community_feeds ADD CONSTRAINT community_feeds_type_check CHECK ((type)::text = ANY (ARRAY[
            'announcement'::text,
            'event'::text,
            'activity'::text,
            'program'::text,
            'update'::text,
            'photo'::text
        ]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE community_feeds DROP CONSTRAINT IF EXISTS community_feeds_type_check');

        DB::table('community_feeds')
            ->where('type', 'photo')
            ->update(['type' => 'activity']);

        DB::statement("ALTER TABLE community_feeds ADD CONSTRAINT announcements_type_check CHECK ((type)::text = ANY (ARRAY[
            'announcement'::text,
            'event'::text,
            'activity'::text,
            'program'::text,
            'update'::text
        ]))");
    }
};
