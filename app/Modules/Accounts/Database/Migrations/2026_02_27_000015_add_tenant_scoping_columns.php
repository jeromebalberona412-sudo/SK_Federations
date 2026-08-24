<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tenantId = DB::table('tenants')->where('code', 'santa_cruz')->value('id');

        if (! $tenantId) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => 'Santa Cruz Federation',
                'code' => 'santa_cruz',
                'municipality' => 'Santa Cruz',
                'province' => 'Laguna',
                'region' => 'IV-A CALABARZON',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('id');
            }
        });

        DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id', 'users_tenant_id_index');
        });

        if (Schema::hasTable('barangays')) {
            Schema::table('barangays', function (Blueprint $table) {
                if (! Schema::hasColumn('barangays', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id');
                }
            });

            DB::table('barangays')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);

            Schema::table('barangays', function (Blueprint $table) {
                $table->dropUnique('barangays_name_unique');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->index('tenant_id', 'barangays_tenant_id_index');
                $table->unique(['tenant_id', 'name'], 'barangays_tenant_name_unique');
            });
        }

        if (Schema::hasTable('official_profiles')) {
            Schema::table('official_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('official_profiles', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id');
                }
            });

            DB::statement('UPDATE official_profiles op SET tenant_id = u.tenant_id FROM users u WHERE u.id = op.user_id AND op.tenant_id IS NULL');

            Schema::table('official_profiles', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->index('tenant_id', 'official_profiles_tenant_id_index');
            });
        }

        if (Schema::hasTable('admin_activity_logs')) {
            Schema::table('admin_activity_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('admin_activity_logs', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('user_id');
                }
            });

            DB::statement('UPDATE admin_activity_logs al SET tenant_id = u.tenant_id FROM users u WHERE u.id = al.user_id AND al.tenant_id IS NULL');

            Schema::table('admin_activity_logs', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                $table->index('tenant_id', 'admin_activity_logs_tenant_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('admin_activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('admin_activity_logs', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex('admin_activity_logs_tenant_id_index');
                $table->dropColumn('tenant_id');
            }
        });

        if (Schema::hasTable('official_profiles')) {
            Schema::table('official_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('official_profiles', 'tenant_id')) {
                    $table->dropForeign(['tenant_id']);
                    $table->dropIndex('official_profiles_tenant_id_index');
                    $table->dropColumn('tenant_id');
                }
            });
        }

        if (Schema::hasTable('barangays')) {
            Schema::table('barangays', function (Blueprint $table) {
                if (Schema::hasColumn('barangays', 'tenant_id')) {
                    $table->dropUnique('barangays_tenant_name_unique');
                    $table->dropForeign(['tenant_id']);
                    $table->dropIndex('barangays_tenant_id_index');
                    $table->unique('name', 'barangays_name_unique');
                    $table->dropColumn('tenant_id');
                }
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex('users_tenant_id_index');
                $table->dropColumn('tenant_id');
            }
        });
    }
};
