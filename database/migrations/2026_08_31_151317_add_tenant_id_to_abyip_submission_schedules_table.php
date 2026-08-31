<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('abyip_submission_schedules')) {
            return;
        }

        if (Schema::hasColumn('abyip_submission_schedules', 'tenant_id')) {
            return;
        }

        Schema::table('abyip_submission_schedules', function (Blueprint $table) {
            if (Schema::hasTable('tenants')) {
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('tenant_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('abyip_submission_schedules') || ! Schema::hasColumn('abyip_submission_schedules', 'tenant_id')) {
            return;
        }

        Schema::table('abyip_submission_schedules', function (Blueprint $table) {
            if (Schema::hasTable('tenants')) {
                $table->dropConstrainedForeignId('tenant_id');
            } else {
                $table->dropColumn('tenant_id');
            }
        });
    }
};
