<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_fed_auth_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 120);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at'], 'sk_fed_auth_audit_event_idx');
            $table->index(['user_id', 'created_at'], 'sk_fed_auth_audit_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_fed_auth_audit_logs');
    }
};
