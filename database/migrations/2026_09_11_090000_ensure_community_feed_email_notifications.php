<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('community_feeds') && ! Schema::hasColumn('community_feeds', 'email_notifications_enabled')) {
            Schema::table('community_feeds', function (Blueprint $table) {
                $table->boolean('email_notifications_enabled')->default(false)->after('link_url');
            });
        }

        if (! Schema::hasTable('community_feed_email_sends')) {
            Schema::create('community_feed_email_sends', function (Blueprint $table) {
                $table->id();
                $table->foreignId('community_feed_id')->constrained('community_feeds')->cascadeOnDelete();
                $table->string('recipient_email');
                $table->unsignedBigInteger('kabataan_registration_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['community_feed_id', 'recipient_email'], 'community_feed_email_sends_unique');
                $table->index('kabataan_registration_id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_feed_email_sends');

        if (Schema::hasTable('community_feeds') && Schema::hasColumn('community_feeds', 'email_notifications_enabled')) {
            Schema::table('community_feeds', function (Blueprint $table) {
                $table->dropColumn('email_notifications_enabled');
            });
        }
    }
};
