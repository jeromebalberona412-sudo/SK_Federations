<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'account_status')) {
                $table->string('account_status', 40)->nullable()->after('status');
            }

            if (! Schema::hasColumn('users', 'turnover_status')) {
                $table->string('turnover_status', 40)->nullable()->after('account_status');
            }

            if (! Schema::hasColumn('users', 'activated_term_id')) {
                $table->foreignId('activated_term_id')->nullable()->after('turnover_status')
                    ->constrained('official_terms')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'turnover_notice_dismissed_until')) {
                $table->timestamp('turnover_notice_dismissed_until')->nullable()->after('activated_term_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'activated_term_id')) {
                $table->dropConstrainedForeignId('activated_term_id');
            }

            foreach (['turnover_notice_dismissed_until', 'turnover_status', 'account_status'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
