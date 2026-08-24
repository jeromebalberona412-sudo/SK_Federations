<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sk_federations_notifications')) {
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

        if (! Schema::hasTable('sk_federations_calendar')) {
            Schema::create('sk_federations_calendar', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('note_date');
                $table->string('title', 255);
                $table->text('content');
                $table->timestamps();

                $table->unique('note_date', 'sk_federations_calendar_note_date_unique');
            });
        }
    }

    public function down(): void
    {
        // Tables are owned by earlier migrations; keep data on rollback of this guard migration.
    }
};
