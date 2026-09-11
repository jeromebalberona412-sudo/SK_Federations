<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_feeds')) {
            return;
        }

        if (! Schema::hasColumn('community_feeds', 'notify_targets')) {
            Schema::table('community_feeds', function (Blueprint $table) {
                $table->json('notify_targets')->nullable()->after('visibility');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('community_feeds') || ! Schema::hasColumn('community_feeds', 'notify_targets')) {
            return;
        }

        Schema::table('community_feeds', function (Blueprint $table) {
            $table->dropColumn('notify_targets');
        });
    }
};
