<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'body')) {
            // Attachment-only messages may omit a text caption.
            DB::statement('ALTER TABLE messages ALTER COLUMN body DROP NOT NULL');
        }

        if (! Schema::hasTable('message_attachments')) {
            Schema::create('message_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->string('file_name', 255);
                $table->text('file_path');
                $table->string('public_id', 512)->nullable();
                $table->string('file_type', 20);
                $table->string('mime_type', 127)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('storage_provider', 32);
                $table->timestamps();

                $table->index('message_id');
                $table->index(['storage_provider', 'file_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');

        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'body')) {
            DB::table('messages')->whereNull('body')->update(['body' => '']);
            DB::statement('ALTER TABLE messages ALTER COLUMN body SET NOT NULL');
        }
    }
};
