<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production-safe repair for ABYIP review columns.
 *
 * Existing migration 2026_06_15_000001_add_abyip_review_columns already defines
 * these columns, but some deployed databases (e.g. Hostinger/Supabase) are
 * missing rejection_reason while application code selects it.
 *
 * This migration is idempotent and never drops/truncates abyip data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('abyip')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.abyip ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');
            DB::statement('ALTER TABLE public.abyip ADD COLUMN IF NOT EXISTS reviewed_by_user_id BIGINT NULL');
            DB::statement('ALTER TABLE public.abyip ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL');

            return;
        }

        if (! Schema::hasColumn('abyip', 'reviewed_at')) {
            Schema::table('abyip', function ($table) {
                $table->timestamp('reviewed_at')->nullable();
            });
        }

        if (! Schema::hasColumn('abyip', 'reviewed_by_user_id')) {
            Schema::table('abyip', function ($table) {
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            });
        }

        if (! Schema::hasColumn('abyip', 'rejection_reason')) {
            Schema::table('abyip', function ($table) {
                $table->text('rejection_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Intentional no-op: never drop review columns or ABYIP data.
    }
};
