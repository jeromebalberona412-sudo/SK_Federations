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

        $this->ensureIndex('sk_fed_auth_audit_logs', 'sk_fed_auth_audit_tenant_idx', ['tenant_id', 'created_at']);
        $this->ensureIndex('sk_fed_auth_audit_logs', 'sk_fed_auth_audit_outcome_idx', ['outcome', 'created_at']);
        $this->ensureIndex('sk_fed_auth_audit_logs', 'sk_fed_auth_resource_idx', ['resource_type', 'resource_id']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('sk_fed_auth_audit_logs')) {
            return;
        }

        Schema::table('sk_fed_auth_audit_logs', function (Blueprint $table) {
            foreach ([
                'sk_fed_auth_audit_tenant_idx',
                'sk_fed_auth_audit_outcome_idx',
                'sk_fed_auth_resource_idx',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable) {
                }
            }

            if (Schema::hasColumn('sk_fed_auth_audit_logs', 'tenant_id')) {
                try {
                    $table->dropForeign(['tenant_id']);
                } catch (\Throwable) {
                }
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('sk_fed_auth_audit_logs', 'tenant_id') ? 'tenant_id' : null,
                Schema::hasColumn('sk_fed_auth_audit_logs', 'actor_email') ? 'actor_email' : null,
                Schema::hasColumn('sk_fed_auth_audit_logs', 'outcome') ? 'outcome' : null,
                Schema::hasColumn('sk_fed_auth_audit_logs', 'resource_type') ? 'resource_type' : null,
                Schema::hasColumn('sk_fed_auth_audit_logs', 'resource_id') ? 'resource_id' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function ensureIndex(string $table, string $index, array $columns): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index, $columns) {
                $blueprint->index($columns, $index);
            });
        } catch (\Throwable) {
            // Index may already exist from ensure_sk_fed_auth_tables.
        }
    }
};
