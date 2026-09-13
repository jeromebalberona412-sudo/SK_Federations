<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production Hostinger/Supabase often lacks Authentication module tables
 * because module migrations were not applied. Create them idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sk_fed_login_attempts')) {
            Schema::create('sk_fed_login_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('email')->index();
                $table->string('ip_address', 45)->index();
                $table->boolean('successful')->default(false);
                $table->text('user_agent')->nullable();
                $table->timestamp('attempted_at')->useCurrent();
                $table->json('metadata')->nullable();
                $table->index(['email', 'successful', 'attempted_at'], 'sk_fed_login_attempt_email_idx');
                $table->index(['ip_address', 'successful', 'attempted_at'], 'sk_fed_login_attempt_ip_idx');
            });
        }

        if (! Schema::hasTable('sk_fed_trusted_devices')) {
            Schema::create('sk_fed_trusted_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('fingerprint', 128);
                $table->string('device_token_hash', 128)->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'fingerprint'], 'sk_fed_trusted_device_unique');
                $table->index(['user_id', 'expires_at'], 'sk_fed_trusted_device_exp_idx');
            });
        } elseif (! Schema::hasColumn('sk_fed_trusted_devices', 'device_token_hash')) {
            Schema::table('sk_fed_trusted_devices', function (Blueprint $table) {
                $table->string('device_token_hash', 128)->nullable()->index()->after('fingerprint');
            });
        }

        if (! Schema::hasTable('sk_fed_auth_audit_logs')) {
            Schema::create('sk_fed_auth_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->string('actor_email', 255)->nullable();
                $table->string('event', 120);
                $table->string('outcome', 20)->nullable();
                $table->string('resource_type', 120)->nullable();
                $table->string('resource_id', 120)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['event', 'created_at'], 'sk_fed_auth_audit_event_idx');
                $table->index(['user_id', 'created_at'], 'sk_fed_auth_audit_user_idx');
                $table->index(['tenant_id', 'created_at'], 'sk_fed_auth_audit_tenant_idx');
                $table->index(['outcome', 'created_at'], 'sk_fed_auth_audit_outcome_idx');
                $table->index(['resource_type', 'resource_id'], 'sk_fed_auth_resource_idx');
            });
        } else {
            Schema::table('sk_fed_auth_audit_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                }
                if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'actor_email')) {
                    $table->string('actor_email', 255)->nullable();
                }
                if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'outcome')) {
                    $table->string('outcome', 20)->nullable();
                }
                if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'resource_type')) {
                    $table->string('resource_type', 120)->nullable();
                }
                if (! Schema::hasColumn('sk_fed_auth_audit_logs', 'resource_id')) {
                    $table->string('resource_id', 120)->nullable();
                }
            });
        }

        if (! Schema::hasTable('sk_fed_email_verified_devices')) {
            Schema::create('sk_fed_email_verified_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('fingerprint', 128);
                $table->timestamp('verified_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index('verified_at', 'sk_fed_verified_device_verified_at_idx');
            });
        }

        if (! Schema::hasTable('sk_fed_feature_flags')) {
            Schema::create('sk_fed_feature_flags', function (Blueprint $table) {
                $table->id();
                $table->string('flag_key', 190)->unique();
                $table->boolean('enabled')->default(false);
                $table->string('description')->nullable();
                $table->unsignedTinyInteger('rollout_percentage')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        $driver = Schema::getConnection()->getDriverName();
        foreach ([
            ['features.device_verification', 'Enable trusted-device challenge flow for SK FED.'],
            ['features.login_alert_notifications', 'Send login alerts for unusual sign-ins.'],
            ['features.suspicious_login_detection', 'Detect suspicious login patterns.'],
        ] as [$key, $description]) {
            if (DB::table('sk_fed_feature_flags')->where('flag_key', $key)->exists()) {
                continue;
            }

            DB::table('sk_fed_feature_flags')->insert([
                'flag_key' => $key,
                'enabled' => $driver === 'pgsql' ? DB::raw('true') : true,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // Keep auth tables; production login depends on them.
    }
};
