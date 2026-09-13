<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        $tenantId = DB::table('tenants')->where('code', 'santa_cruz')->value('id');

        if (! $tenantId) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => 'Santa Cruz Federation',
                'code' => 'santa_cruz',
                'municipality' => 'Santa Cruz',
                'province' => 'Laguna',
                'region' => 'IV-A CALABARZON',
                // PostgreSQL rejects integer 1 for boolean columns.
                'is_active' => $driver === 'pgsql' ? DB::raw('true') : true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'tenant_id')) {
            DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            $this->ensureForeignKey('users', 'tenant_id', 'tenants', 'id', 'cascade');
            $this->ensureIndex('users', 'users_tenant_id_index', ['tenant_id']);
        }

        if (Schema::hasTable('barangays')) {
            if (! Schema::hasColumn('barangays', 'tenant_id')) {
                Schema::table('barangays', function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->after('id');
                });
            }

            DB::table('barangays')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);

            $this->dropIndexIfExists('barangays', 'barangays_name_unique');
            $this->ensureForeignKey('barangays', 'tenant_id', 'tenants', 'id', 'cascade');
            $this->ensureIndex('barangays', 'barangays_tenant_id_index', ['tenant_id']);
            $this->ensureUnique('barangays', 'barangays_tenant_name_unique', ['tenant_id', 'name']);
        }

        if (Schema::hasTable('official_profiles')) {
            if (! Schema::hasColumn('official_profiles', 'tenant_id')) {
                Schema::table('official_profiles', function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->after('id');
                });
            }

            if ($driver === 'pgsql') {
                DB::statement('UPDATE official_profiles op SET tenant_id = u.tenant_id FROM users u WHERE u.id = op.user_id AND op.tenant_id IS NULL');
            } else {
                DB::statement('UPDATE official_profiles op INNER JOIN users u ON u.id = op.user_id SET op.tenant_id = u.tenant_id WHERE op.tenant_id IS NULL');
            }

            $this->ensureForeignKey('official_profiles', 'tenant_id', 'tenants', 'id', 'cascade');
            $this->ensureIndex('official_profiles', 'official_profiles_tenant_id_index', ['tenant_id']);
        }

        if (Schema::hasTable('admin_activity_logs')) {
            if (! Schema::hasColumn('admin_activity_logs', 'tenant_id')) {
                Schema::table('admin_activity_logs', function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->after('user_id');
                });
            }

            if ($driver === 'pgsql') {
                DB::statement('UPDATE admin_activity_logs al SET tenant_id = u.tenant_id FROM users u WHERE u.id = al.user_id AND al.tenant_id IS NULL');
            } else {
                DB::statement('UPDATE admin_activity_logs al INNER JOIN users u ON u.id = al.user_id SET al.tenant_id = u.tenant_id WHERE al.tenant_id IS NULL');
            }

            $this->ensureForeignKey('admin_activity_logs', 'tenant_id', 'tenants', 'id', 'null');
            $this->ensureIndex('admin_activity_logs', 'admin_activity_logs_tenant_id_index', ['tenant_id']);
        }
    }

    public function down(): void
    {
        // Keep tenant scoping columns on rollback; production data depends on them.
    }

    private function ensureIndex(string $table, string $index, array $columns): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index, $columns) {
                $blueprint->index($columns, $index);
            });
        } catch (\Throwable) {
            // Index may already exist.
        }
    }

    private function ensureUnique(string $table, string $index, array $columns): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index, $columns) {
                $blueprint->unique($columns, $index);
            });
        } catch (\Throwable) {
            // Unique index may already exist.
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropUnique($index);
            });
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropIndex($index);
                });
            } catch (\Throwable) {
                // Index may not exist.
            }
        }
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete
    ): void {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $onDelete) {
                $fk = $blueprint->foreign($column)->references($referencedColumn)->on($referencedTable);
                if ($onDelete === 'cascade') {
                    $fk->cascadeOnDelete();
                } else {
                    $fk->nullOnDelete();
                }
            });
        } catch (\Throwable) {
            // Foreign key may already exist.
        }
    }
};
