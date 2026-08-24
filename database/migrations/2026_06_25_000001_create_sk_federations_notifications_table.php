<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_federations_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50)->default('general');
            $table->string('title', 255);
            $table->text('body');
            $table->string('action_url', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at'], 'sk_federations_notifications_user_id_read_at_index');
            $table->index(['user_id', 'created_at'], 'sk_federations_notifications_user_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_federations_notifications');
    }
};
