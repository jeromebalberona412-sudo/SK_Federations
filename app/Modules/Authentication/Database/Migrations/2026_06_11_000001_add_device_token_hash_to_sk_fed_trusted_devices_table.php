<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sk_fed_trusted_devices')) {
            return;
        }

        Schema::table('sk_fed_trusted_devices', function (Blueprint $table): void {
            if (! Schema::hasColumn('sk_fed_trusted_devices', 'device_token_hash')) {
                $table->string('device_token_hash', 64)->nullable()->after('fingerprint');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sk_fed_trusted_devices')) {
            return;
        }

        Schema::table('sk_fed_trusted_devices', function (Blueprint $table): void {
            if (Schema::hasColumn('sk_fed_trusted_devices', 'device_token_hash')) {
                $table->dropColumn('device_token_hash');
            }
        });
    }
};
