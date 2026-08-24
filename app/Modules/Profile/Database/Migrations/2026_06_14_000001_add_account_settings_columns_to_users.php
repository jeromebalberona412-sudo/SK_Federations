<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $additions = [
            'pending_email' => fn (Blueprint $table) => $table->string('pending_email')->nullable(),
            'email_change_token' => fn (Blueprint $table) => $table->string('email_change_token')->nullable(),
            'email_change_token_expires_at' => fn (Blueprint $table) => $table->timestamp('email_change_token_expires_at')->nullable(),
            'email_change_verified_at' => fn (Blueprint $table) => $table->timestamp('email_change_verified_at')->nullable(),
            'email_change_last_sent_at' => fn (Blueprint $table) => $table->timestamp('email_change_last_sent_at')->nullable(),
            'pending_password' => fn (Blueprint $table) => $table->string('pending_password')->nullable(),
            'password_change_token' => fn (Blueprint $table) => $table->string('password_change_token')->nullable(),
            'password_change_token_expires_at' => fn (Blueprint $table) => $table->timestamp('password_change_token_expires_at')->nullable(),
            'password_change_last_sent_at' => fn (Blueprint $table) => $table->timestamp('password_change_last_sent_at')->nullable(),
            'must_change_password' => fn (Blueprint $table) => $table->boolean('must_change_password')->default(false),
        ];

        foreach ($additions as $column => $definition) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        $columns = [
            'pending_email',
            'email_change_token',
            'email_change_token_expires_at',
            'email_change_verified_at',
            'email_change_last_sent_at',
            'pending_password',
            'password_change_token',
            'password_change_token_expires_at',
            'password_change_last_sent_at',
            'must_change_password',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
