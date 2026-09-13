<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production Hostinger DBs were missing users.deleted_at while the User model
 * already uses SoftDeletes. Keep this in database/migrations so plain
 * `php artisan migrate` always applies it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['role', 'deleted_at'], 'users_role_deleted_at_index');
            });
        } catch (\Throwable) {
            // Index may already exist, or role column may be absent on legacy schemas.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'deleted_at')) {
            return;
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_role_deleted_at_index');
            });
        } catch (\Throwable) {
            // ignore
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
