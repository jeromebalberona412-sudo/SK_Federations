<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_reactions')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE message_reactions REPLICA IDENTITY FULL');
        } catch (\Throwable) {
            // Non-Postgres or insufficient privileges — ignore.
        }

        try {
            DB::statement('ALTER PUBLICATION supabase_realtime ADD TABLE message_reactions');
        } catch (\Throwable) {
            // Already added, or publication unavailable — ignore.
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER PUBLICATION supabase_realtime DROP TABLE message_reactions');
        } catch (\Throwable) {
            // ignore
        }
    }
};
