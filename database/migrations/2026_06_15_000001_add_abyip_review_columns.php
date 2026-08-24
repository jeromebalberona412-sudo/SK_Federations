<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('abyip')) {
            return;
        }

        Schema::table('abyip', function (Blueprint $table) {
            if (! Schema::hasColumn('abyip', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('abyip', 'reviewed_by_user_id')) {
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('abyip', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('reviewed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('abyip')) {
            return;
        }

        Schema::table('abyip', function (Blueprint $table) {
            foreach (['rejection_reason', 'reviewed_by_user_id', 'reviewed_at'] as $column) {
                if (Schema::hasColumn('abyip', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
