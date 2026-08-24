<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sk_fed_auth_audit_logs')) {
            return;
        }

        Schema::table('sk_fed_auth_audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained('tenants')->nullOnDelete();
            }

            if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'actor_email')) {
                $table->string('actor_email', 255)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'outcome')) {
                $table->string('outcome', 20)->nullable()->after('event');
            }

            if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'resource_type')) {
                $table->string('resource_type', 120)->nullable()->after('outcome');
            }

            if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'resource_id')) {
                $table->string('resource_id', 120)->nullable()->after('resource_type');
            }

        });

        Schema::table('sk_fed_auth_audit_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'sk_fed_auth_audit_tenant_idx');
            $table->index(['outcome', 'created_at'], 'sk_fed_auth_audit_outcome_idx');
            $table->index(['resource_type', 'resource_id'], 'sk_fed_auth_resource_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sk_fed_auth_audit_logs')) {
            return;
        }

        Schema::table('sk_fed_auth_audit_logs', function (Blueprint $table) {
            $table->dropIndex('sk_fed_auth_audit_tenant_idx');
            $table->dropIndex('sk_fed_auth_audit_outcome_idx');
            $table->dropIndex('sk_fed_auth_resource_idx');

            if (Schema::hasColumn('sk_fed_auth_audit_logs', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
            }

            $table->dropColumn([
                'tenant_id',
                'actor_email',
                'outcome',
                'resource_type',
                'resource_id',
            ]);
        });
    }
};
