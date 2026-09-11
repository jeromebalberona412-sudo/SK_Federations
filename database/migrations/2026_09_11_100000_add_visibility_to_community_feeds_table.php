<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_feeds')) {
            return;
        }

        if (! Schema::hasColumn('community_feeds', 'visibility')) {
            Schema::table('community_feeds', function (Blueprint $table) {
                $table->string('visibility', 20)->default('public')->after('is_federation_wide');
            });
        }

        DB::table('community_feeds')
            ->where(function ($query) {
                $query->whereNull('visibility')->orWhere('visibility', '');
            })
            ->update(['visibility' => 'public']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('community_feeds') || ! Schema::hasColumn('community_feeds', 'visibility')) {
            return;
        }

        Schema::table('community_feeds', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
