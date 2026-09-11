<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sk_federations_calendar', function (Blueprint $table) {
            if (! Schema::hasColumn('sk_federations_calendar', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        DB::statement('ALTER TABLE sk_federations_calendar DROP CONSTRAINT IF EXISTS sk_federations_calendar_note_date_unique');
        DB::statement('DROP INDEX IF EXISTS sk_federations_calendar_note_date_unique');

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS sk_federations_calendar_note_date_unique
             ON sk_federations_calendar (note_date)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sk_federations_calendar_note_date_unique');
        DB::statement('ALTER TABLE sk_federations_calendar DROP CONSTRAINT IF EXISTS sk_federations_calendar_note_date_unique');

        Schema::table('sk_federations_calendar', function (Blueprint $table) {
            $table->unique('note_date', 'sk_federations_calendar_note_date_unique');
        });

        Schema::table('sk_federations_calendar', function (Blueprint $table) {
            if (Schema::hasColumn('sk_federations_calendar', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
