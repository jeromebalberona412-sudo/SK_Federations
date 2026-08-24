<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_turnover_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_turnover_id')->constrained('federation_turnovers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('official_term_id')->nullable()->constrained('official_terms')->nullOnDelete();
            $table->string('position', 50);
            $table->string('complete_name');
            $table->string('email');
            $table->string('contact_number', 20)->nullable();
            $table->string('municipality')->default('Santa Cruz');
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            $table->enum('status', [
                'pending',
                'invited',
                'account_created',
                'activated',
            ])->default('pending');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('account_setup_completed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->json('registration_payload')->nullable();
            $table->timestamps();

            $table->index(['federation_turnover_id', 'position']);
            $table->index(['email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_turnover_registrations');
    }
};
