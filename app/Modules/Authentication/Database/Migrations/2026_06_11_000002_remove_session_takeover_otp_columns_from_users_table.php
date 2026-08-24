<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['otp_code', 'otp_expires_at', 'otp_attempts', 'otp_last_sent_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'otp_code')) {
                $table->string('otp_code', 255)->nullable()->after('last_ip');
            }

            if (! Schema::hasColumn('users', 'otp_expires_at')) {
                $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            }

            if (! Schema::hasColumn('users', 'otp_attempts')) {
                $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_expires_at');
            }

            if (! Schema::hasColumn('users', 'otp_last_sent_at')) {
                $table->timestamp('otp_last_sent_at')->nullable()->after('otp_attempts');
            }
        });
    }
};
