<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Federation Google Drive folder sync config.
 *
 * If Officials already created community_feed_drive_folders (unique barangay_id),
 * do not recreate. Make barangay_id nullable so Federations can store user-scoped
 * rows with barangay_id NULL. Postgres UNIQUE allows multiple NULLs, so Officials
 * one-row-per-barangay uniqueness is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_feed_drive_folders')) {
            Schema::create('community_feed_drive_folders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('barangay_id')->nullable();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                if (Schema::hasTable('community_feeds')) {
                    $table->foreignId('community_feed_id')
                        ->nullable()
                        ->constrained('community_feeds')
                        ->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('community_feed_id')->nullable()->index();
                }
                $table->string('folder_id', 128);
                $table->string('folder_url', 2048);
                $table->string('folder_name', 512)->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->string('last_sync_status', 32)->nullable();
                $table->text('last_sync_message')->nullable();
                $table->unsignedSmallInteger('synced_video_count')->default(0);
                $table->timestamps();

                $table->unique('barangay_id');
                $table->index('folder_id');
                $table->index(['user_id', 'barangay_id']);
            });

            if (Schema::hasTable('barangays')) {
                try {
                    Schema::table('community_feed_drive_folders', function (Blueprint $table) {
                        $table->foreign('barangay_id')
                            ->references('id')
                            ->on('barangays')
                            ->nullOnDelete();
                    });
                } catch (Throwable) {
                    // FK may already exist or barangays may use a different name.
                }
            }

            $this->ensureFederationUserPartialUnique();

            return;
        }

        Schema::table('community_feed_drive_folders', function (Blueprint $table) {
            if (! Schema::hasColumn('community_feed_drive_folders', 'community_feed_id')) {
                if (Schema::hasTable('community_feeds')) {
                    $table->foreignId('community_feed_id')
                        ->nullable()
                        ->after('user_id')
                        ->constrained('community_feeds')
                        ->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('community_feed_id')->nullable()->after('user_id')->index();
                }
            }

            if (! Schema::hasColumn('community_feed_drive_folders', 'last_sync_status')) {
                $table->string('last_sync_status', 32)->nullable()->after('last_synced_at');
            }
            if (! Schema::hasColumn('community_feed_drive_folders', 'last_sync_message')) {
                $table->text('last_sync_message')->nullable()->after('last_sync_status');
            }
            if (! Schema::hasColumn('community_feed_drive_folders', 'synced_video_count')) {
                $table->unsignedSmallInteger('synced_video_count')->default(0)->after('last_sync_message');
            }
        });

        $this->makeBarangayIdNullable();
        $this->ensureFederationUserPartialUnique();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('DROP INDEX IF EXISTS community_feed_drive_folders_user_null_barangay_uidx');
            } catch (Throwable) {
            }
        }

        // Do not drop the table — Officials may own it on a shared database.
    }

    private function makeBarangayIdNullable(): void
    {
        if (! Schema::hasColumn('community_feed_drive_folders', 'barangay_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        try {
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE community_feed_drive_folders ALTER COLUMN barangay_id DROP NOT NULL');
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                Schema::table('community_feed_drive_folders', function (Blueprint $table) {
                    $table->unsignedBigInteger('barangay_id')->nullable()->change();
                });
            }
        } catch (Throwable) {
            // Column may already be nullable.
        }
    }

    private function ensureFederationUserPartialUnique(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS community_feed_drive_folders_user_null_barangay_uidx
                 ON community_feed_drive_folders (user_id)
                 WHERE barangay_id IS NULL'
            );
        } catch (Throwable) {
            // Index may already exist.
        }
    }
};
