<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (! Schema::hasColumn('messages', 'edited_at')) {
                    $table->timestamp('edited_at')->nullable();
                }
                if (! Schema::hasColumn('messages', 'deleted_for_all_at')) {
                    $table->timestamp('deleted_for_all_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('message_hides')) {
            Schema::create('message_hides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id');
                $table->string('user_type', 32);
                $table->timestamps();

                $table->unique(['message_id', 'user_id', 'user_type']);
                $table->index(['user_id', 'user_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_hides');

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (Schema::hasColumn('messages', 'edited_at')) {
                    $table->dropColumn('edited_at');
                }
                if (Schema::hasColumn('messages', 'deleted_for_all_at')) {
                    $table->dropColumn('deleted_for_all_at');
                }
            });
        }
    }
};
