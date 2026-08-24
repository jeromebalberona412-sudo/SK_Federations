<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'active_session_id')) {
                $table->string('active_session_id', 255)->nullable()->after('remember_token');
            }

            if (! Schema::hasColumn('users', 'last_seen')) {
                $table->timestamp('last_seen')->nullable()->after('active_session_id');
            }

            if (! Schema::hasColumn('users', 'active_device')) {
                $table->string('active_device', 255)->nullable()->after('last_seen');
            }

            if (! Schema::hasColumn('users', 'last_ip')) {
                $table->string('last_ip', 45)->nullable()->after('active_device');
            }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = [
                'active_session_id',
                'last_seen',
                'active_device',
                'last_ip',
                'otp_code',
                'otp_expires_at',
                'otp_attempts',
                'otp_last_sent_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
