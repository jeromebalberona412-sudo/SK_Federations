<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->index(['action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('admin_activity_logs', 'action')) {
                $table->dropIndex('admin_activity_logs_action_created_at_index');
                $table->dropColumn('action');
            }

            if (Schema::hasColumn('admin_activity_logs', 'entity_id')) {
                $table->dropIndex('admin_activity_logs_entity_type_entity_id_index');
                $table->dropColumn('entity_id');
            }

            if (Schema::hasColumn('admin_activity_logs', 'entity_type')) {
                $table->dropColumn('entity_type');
            }
        });
    }
};
