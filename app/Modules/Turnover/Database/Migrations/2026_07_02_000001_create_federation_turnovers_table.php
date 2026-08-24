<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_turnovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('current_term_id')->nullable()->constrained('official_terms')->nullOnDelete();
            $table->foreignId('new_term_id')->nullable()->constrained('official_terms')->nullOnDelete();
            $table->foreignId('previous_president_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('previous_vice_president_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_president_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_vice_president_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'pending_registration',
                'pending_account_setup',
                'pending_confirmation',
                'completed',
                'cancelled',
            ])->default('pending_registration');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('started_ip', 45)->nullable();
            $table->string('started_user_agent')->nullable();
            $table->string('confirmed_ip', 45)->nullable();
            $table->string('confirmed_user_agent')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('current_term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_turnovers');
    }
};
