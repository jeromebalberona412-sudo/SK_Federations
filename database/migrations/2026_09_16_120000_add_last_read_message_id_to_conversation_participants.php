<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_participants')) {
            return;
        }

        if (! Schema::hasColumn('conversation_participants', 'last_read_message_id')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                $table->unsignedBigInteger('last_read_message_id')->nullable()->after('last_read_at');
                $table->index(['user_id', 'user_type', 'last_read_message_id'], 'cp_user_last_read_msg_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversation_participants')) {
            return;
        }

        if (Schema::hasColumn('conversation_participants', 'last_read_message_id')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                $table->dropIndex('cp_user_last_read_msg_idx');
                $table->dropColumn('last_read_message_id');
            });
        }
    }
};
