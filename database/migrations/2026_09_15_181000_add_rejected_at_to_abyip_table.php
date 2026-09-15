<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('abyip')) {
            return;
        }

        Schema::table('abyip', function (Blueprint $table) {
            if (! Schema::hasColumn('abyip', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
            }
        });

        if (Schema::hasColumn('abyip', 'rejected_at') && Schema::hasColumn('abyip', 'reviewed_at')) {
            DB::table('abyip')
                ->where('status', 'rejected')
                ->whereNull('rejected_at')
                ->whereNotNull('reviewed_at')
                ->update(['rejected_at' => DB::raw('reviewed_at')]);
        }

        if (Schema::hasColumn('abyip', 'rejected_at') && Schema::hasColumn('abyip', 'status')) {
            $indexName = 'abyip_status_rejected_at_index';
            $hasIndex = method_exists(Schema::class, 'hasIndex')
                ? Schema::hasIndex('abyip', $indexName)
                : false;

            if (! $hasIndex) {
                Schema::table('abyip', function (Blueprint $table) use ($indexName) {
                    $table->index(['status', 'rejected_at'], $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('abyip')) {
            return;
        }

        $indexName = 'abyip_status_rejected_at_index';
        if (method_exists(Schema::class, 'hasIndex') && Schema::hasIndex('abyip', $indexName)) {
            Schema::table('abyip', function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }

        if (Schema::hasColumn('abyip', 'rejected_at')) {
            Schema::table('abyip', function (Blueprint $table) {
                $table->dropColumn('rejected_at');
            });
        }
    }
};
