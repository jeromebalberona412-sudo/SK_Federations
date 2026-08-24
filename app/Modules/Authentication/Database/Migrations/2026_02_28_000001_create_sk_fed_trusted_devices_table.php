<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_fed_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint', 128);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'sk_fed_trusted_device_unique');
            $table->index(['user_id', 'expires_at'], 'sk_fed_trusted_device_exp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_fed_trusted_devices');
    }
};
