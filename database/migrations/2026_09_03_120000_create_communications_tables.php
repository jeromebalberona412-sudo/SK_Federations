<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->string('conversation_type', 20)->default('private');
                $table->timestamps();

                $table->index('conversation_type');
            });
        }

        if (! Schema::hasTable('conversation_participants')) {
            Schema::create('conversation_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('user_type', 20);
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id', 'user_type'], 'conv_participants_unique');
                $table->index(['user_id', 'user_type']);
            });
        }

        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->string('sender_type', 20);
                $table->text('body');
                $table->string('message_type', 20)->default('text');
                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
                $table->index(['sender_id', 'sender_type']);
            });
        }

        if (! Schema::hasTable('calls')) {
            Schema::create('calls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
                $table->string('caller_type', 20);
                $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
                $table->string('receiver_type', 20);
                $table->string('call_type', 10);
                $table->string('status', 20)->default('ringing');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
                $table->index(['caller_id', 'caller_type']);
                $table->index(['receiver_id', 'receiver_type']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
