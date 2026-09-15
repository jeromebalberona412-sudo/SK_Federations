<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'avatar_url')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->string('avatar_url', 500)->nullable()->after('max_members');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'avatar_url')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->dropColumn('avatar_url');
            });
        }
    }
};
