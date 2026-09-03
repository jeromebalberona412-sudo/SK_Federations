<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('message_attachments') && ! Schema::hasColumn('message_attachments', 'file_data')) {
            // Add BYTEA column for direct PostgreSQL binary storage of documents.
            // Nullable so existing Cloudinary/Supabase-stored attachments are unaffected.
            DB::statement('ALTER TABLE message_attachments ADD COLUMN file_data BYTEA NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('message_attachments') && Schema::hasColumn('message_attachments', 'file_data')) {
            Schema::table('message_attachments', function (Blueprint $table) {
                $table->dropColumn('file_data');
            });
        }
    }
};
