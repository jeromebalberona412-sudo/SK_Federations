<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_feeds') || ! Schema::hasColumn('community_feeds', 'barangay_id')) {
            throw new RuntimeException('Cannot allow federation-wide posts because community_feeds.barangay_id does not exist.');
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_feeds ALTER COLUMN barangay_id DROP NOT NULL');

            return;
        }

        Schema::table('community_feeds', function (Blueprint $table): void {
            $table->unsignedBigInteger('barangay_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('community_feeds') || ! Schema::hasColumn('community_feeds', 'barangay_id')) {
            return;
        }

        if (DB::table('community_feeds')->whereNull('barangay_id')->exists()) {
            throw new RuntimeException('Cannot make community_feeds.barangay_id required while federation-wide posts have a NULL barangay_id.');
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_feeds ALTER COLUMN barangay_id SET NOT NULL');

            return;
        }

        Schema::table('community_feeds', function (Blueprint $table): void {
            $table->unsignedBigInteger('barangay_id')->nullable(false)->change();
        });
    }
};
