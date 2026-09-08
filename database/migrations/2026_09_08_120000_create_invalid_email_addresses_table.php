<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invalid_email_addresses')) {
            return;
        }

        Schema::create('invalid_email_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->string('normalized_email', 255);
            $table->string('status', 40)->default('temporarily_invalid');
            $table->unsignedInteger('failure_count')->default(1);
            $table->string('last_failure_reason', 255)->nullable();
            $table->timestamp('first_detected_at')->nullable();
            $table->timestamp('last_detected_at')->nullable();
            $table->timestamp('retry_after')->nullable();
            $table->timestamp('permanently_blocked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('normalized_email');
            $table->index('status');
            $table->index('retry_after');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invalid_email_addresses');
    }
};
