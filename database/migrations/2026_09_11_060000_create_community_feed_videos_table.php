<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_feed_videos')) {
            Schema::create('community_feed_videos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('community_feed_id')
                    ->constrained('community_feeds')
                    ->cascadeOnDelete();
                $table->string('provider', 32);
                $table->text('video_url');
                $table->string('public_id', 512)->nullable();
                $table->string('google_drive_file_id', 128)->nullable();
                $table->string('google_drive_folder_id', 128)->nullable();
                $table->string('name', 512)->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('bytes')->nullable();
                $table->timestamp('drive_created_at')->nullable();
                $table->string('web_view_link', 2048)->nullable();
                $table->string('status', 32)->default('active');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamp('created_at')->nullable();

                $table->unique('google_drive_file_id');
                $table->index(['community_feed_id', 'sort_order']);
                $table->index('provider');
                $table->index('google_drive_folder_id', 'cf_videos_drive_folder_idx');
                $table->index(['google_drive_folder_id', 'status'], 'cf_videos_drive_folder_status_idx');
            });

            return;
        }

        Schema::table('community_feed_videos', function (Blueprint $table) {
            if (! Schema::hasColumn('community_feed_videos', 'google_drive_folder_id')) {
                $table->string('google_drive_folder_id', 128)->nullable()->after('google_drive_file_id');
                $table->index('google_drive_folder_id', 'cf_videos_drive_folder_idx');
            }
            if (! Schema::hasColumn('community_feed_videos', 'name')) {
                $table->string('name', 512)->nullable()->after('google_drive_folder_id');
            }
            if (! Schema::hasColumn('community_feed_videos', 'drive_created_at')) {
                $table->timestamp('drive_created_at')->nullable()->after('name');
            }
            if (! Schema::hasColumn('community_feed_videos', 'web_view_link')) {
                $table->string('web_view_link', 2048)->nullable()->after('drive_created_at');
            }
            if (! Schema::hasColumn('community_feed_videos', 'status')) {
                $table->string('status', 32)->default('active')->after('web_view_link');
                $table->index(['google_drive_folder_id', 'status'], 'cf_videos_drive_folder_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_feed_videos');
    }
};
