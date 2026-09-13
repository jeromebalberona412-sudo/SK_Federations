<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_activity_logs')) {
            return;
        }

        Schema::table('admin_activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_activity_logs', 'action')) {
                $table->string('action', 120)->nullable()->after('event_type');
            }

            if (! Schema::hasColumn('admin_activity_logs', 'entity_type')) {
                $table->string('entity_type', 120)->nullable()->after('action');
            }

            if (! Schema::hasColumn('admin_activity_logs', 'entity_id')) {
                $table->string('entity_id', 120)->nullable()->after('entity_type');
            }

            try {
                $table->index(['action', 'created_at']);
            } catch (\Throwable) {
            }

            try {
                $table->index(['entity_type', 'entity_id']);
            } catch (\Throwable) {
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_activity_logs')) {
            return;
        }

        Schema::table('admin_activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('admin_activity_logs', 'action')) {
                try {
                    $table->dropIndex('admin_activity_logs_action_created_at_index');
                } catch (\Throwable) {
                }
                $table->dropColumn('action');
            }

            if (Schema::hasColumn('admin_activity_logs', 'entity_id')) {
                try {
                    $table->dropIndex('admin_activity_logs_entity_type_entity_id_index');
                } catch (\Throwable) {
                }
                $table->dropColumn('entity_id');
            }

            if (Schema::hasColumn('admin_activity_logs', 'entity_type')) {
                $table->dropColumn('entity_type');
            }
        });
    }
};
