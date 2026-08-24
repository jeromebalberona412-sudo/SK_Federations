<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcement_images')) {
            Schema::create('announcement_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
                $table->text('image_url');
                $table->string('public_id', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamp('created_at')->nullable();

                $table->index(['announcement_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_images');
    }
};
