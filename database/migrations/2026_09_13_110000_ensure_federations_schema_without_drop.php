<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Safe Federations schema repair:
 * - create missing tables only (IF NOT EXISTS)
 * - add missing community_feeds columns used by Federation portal
 * - NEVER drop tables/data
 * - NEVER recreate legacy announcements* tables (app uses community_feeds)
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ensureBarangayLogosTable();
        $this->ensureCommunityFeedsColumns();
        $this->ensureCommunityFeedsBarangayNullable();
        $this->ensureCommunityFeedsTypeCheck();
        $this->ensureCommunityFeedImagesFkColumn();
    }

    public function down(): void
    {
        // Intentional no-op: never drop repaired Federations schema.
    }

    private function ensureBarangayLogosTable(): void
    {
        if (Schema::hasTable('barangay_logos')) {
            return;
        }

        Schema::create('barangay_logos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('cloudinary_public_id', 255);
            $table->string('cloudinary_version', 32)->nullable();
            $table->string('url', 255);
            $table->timestamps();
            $table->unique(['barangay_id', 'tenant_id']);
        });
    }

    private function ensureCommunityFeedsColumns(): void
    {
        if (! Schema::hasTable('community_feeds')) {
            return;
        }

        if (! Schema::hasColumn('community_feeds', 'is_federation_wide')) {
            Schema::table('community_feeds', function (Blueprint $table) {
                $table->boolean('is_federation_wide')->default(false);
            });
        }

        if (! Schema::hasColumn('community_feeds', 'share_token')) {
            Schema::table('community_feeds', function (Blueprint $table) {
                $table->string('share_token', 26)->nullable()->unique();
            });
        }

        $rows = DB::table('community_feeds')->whereNull('share_token')->select('id')->orderBy('id')->cursor();
        foreach ($rows as $row) {
            DB::table('community_feeds')
                ->where('id', $row->id)
                ->update(['share_token' => (string) Str::ulid()]);
        }

        if (Schema::hasColumn('community_feeds', 'share_token') && DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_feeds ALTER COLUMN share_token SET NOT NULL');
        }

        if (! Schema::hasColumn('community_feeds', 'visibility')) {
            Schema::table('community_feeds', function (Blueprint $table) {
                $table->string('visibility', 20)->default('public');
            });
        }

        DB::table('community_feeds')
            ->where(function ($query) {
                $query->whereNull('visibility')->orWhere('visibility', '');
            })
            ->update(['visibility' => 'public']);

        if (! Schema::hasColumn('community_feeds', 'notify_targets')) {
            Schema::table('community_feeds', function (Blueprint $table) {
                $table->json('notify_targets')->nullable();
            });
        }
    }

    private function ensureCommunityFeedsBarangayNullable(): void
    {
        if (! Schema::hasTable('community_feeds') || ! Schema::hasColumn('community_feeds', 'barangay_id')) {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $nullable = DB::selectOne(
            "SELECT is_nullable
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = 'community_feeds'
               AND column_name = 'barangay_id'"
        );

        if ($nullable && strtoupper((string) $nullable->is_nullable) === 'NO') {
            DB::statement('ALTER TABLE community_feeds ALTER COLUMN barangay_id DROP NOT NULL');
        }
    }

    private function ensureCommunityFeedsTypeCheck(): void
    {
        if (! Schema::hasTable('community_feeds') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE community_feeds DROP CONSTRAINT IF EXISTS announcements_type_check');
        DB::statement('ALTER TABLE community_feeds DROP CONSTRAINT IF EXISTS community_feeds_type_check');
        DB::statement("ALTER TABLE community_feeds ADD CONSTRAINT community_feeds_type_check CHECK ((type)::text = ANY (ARRAY[
            'announcement'::text,
            'event'::text,
            'activity'::text,
            'program'::text,
            'update'::text,
            'photo'::text
        ]))");
    }

    private function ensureCommunityFeedImagesFkColumn(): void
    {
        if (! Schema::hasTable('community_feed_images')) {
            return;
        }

        if (Schema::hasColumn('community_feed_images', 'announcement_id')
            && ! Schema::hasColumn('community_feed_images', 'community_feed_id')
        ) {
            Schema::table('community_feed_images', function (Blueprint $table) {
                $table->renameColumn('announcement_id', 'community_feed_id');
            });
        }
    }
};
