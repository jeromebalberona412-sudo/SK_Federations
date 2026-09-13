<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy announcements table was renamed to community_feeds.
        $tableName = Schema::hasTable('community_feeds')
            ? 'community_feeds'
            : (Schema::hasTable('announcements') ? 'announcements' : null);

        if ($tableName === null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'is_archived')) {
                $table->boolean('is_archived')->default(false);
            }

            if (! Schema::hasColumn($tableName, 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }

            if (! Schema::hasColumn($tableName, 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No-op: archive columns are required by Community Feed / Deleted Posts.
    }
};
