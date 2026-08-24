<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_fed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->index();
            $table->string('ip_address', 45)->index();
            $table->boolean('successful')->default(false);
            $table->text('user_agent')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->json('metadata')->nullable();

            $table->index(['email', 'successful', 'attempted_at'], 'sk_fed_login_attempt_email_idx');
            $table->index(['ip_address', 'successful', 'attempted_at'], 'sk_fed_login_attempt_ip_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_fed_login_attempts');
    }
};
