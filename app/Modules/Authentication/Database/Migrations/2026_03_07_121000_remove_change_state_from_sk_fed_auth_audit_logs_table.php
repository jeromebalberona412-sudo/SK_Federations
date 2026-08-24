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
            $columnsToDrop = [];

            if (Schema::hasColumn('sk_fed_auth_audit_logs', 'before_state')) {
                $columnsToDrop[] = 'before_state';
            }

            if (Schema::hasColumn('sk_fed_auth_audit_logs', 'after_state')) {
                $columnsToDrop[] = 'after_state';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sk_fed_auth_audit_logs')) {
            return;
        }

        Schema::table('sk_fed_auth_audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'before_state')) {
                $table->json('before_state')->nullable()->after('metadata');
            }

            if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'after_state')) {
                $table->json('after_state')->nullable()->after('before_state');
            }
        });
    }
};
