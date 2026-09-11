<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_feeds')) {
            return;
        }

        if (! Schema::hasColumn('community_feeds', 'share_token')) {
            Schema::table('community_feeds', function (Blueprint $table) {
                $table->string('share_token', 26)->nullable()->unique();
            });
        }

        $rows = DB::table('community_feeds')->whereNull('share_token')->select('id')->orderBy('id')->cursor();
        foreach ($rows as $row) {
            DB::table('community_feeds')
                ->where('id', $row->id)
                ->update(['share_token' => (string) Str::ulid()]);
        }

        if (Schema::hasColumn('community_feeds', 'share_token')) {
            DB::statement('ALTER TABLE community_feeds ALTER COLUMN share_token SET NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('community_feeds') || ! Schema::hasColumn('community_feeds', 'share_token')) {
            return;
        }

        Schema::table('community_feeds', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
