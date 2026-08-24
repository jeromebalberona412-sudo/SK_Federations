<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 30)->default('user')->after('password');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 30)->nullable()->after('role');
            }

            if (! Schema::hasColumn('users', 'lockout_count')) {
                $table->unsignedInteger('lockout_count')->default(0)->after('remember_token');
            }

            if (! Schema::hasColumn('users', 'lockout_until')) {
                $table->timestamp('lockout_until')->nullable()->after('lockout_count');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('lockout_until');
            }

            if (! Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }
        });

        if (Schema::hasTable('tenants') && Schema::hasColumn('users', 'tenant_id')) {
            $tenantCode = (string) config('sk_fed_auth.tenant_code', 'santa_cruz');
            $tenantId = DB::table('tenants')->where('code', $tenantCode)->value('id');

            if ($tenantId) {
                DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }
        }

        $this->createIndexIfMissing('users', 'users_role_index', ['role']);
        $this->createIndexIfMissing('users', 'users_tenant_id_index', ['tenant_id']);
        $this->createIndexIfMissing('users', 'users_lockout_until_index', ['lockout_until']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'lockout_until')) {
                $table->dropIndex('users_lockout_until_index');
            }

            if (Schema::hasColumn('users', 'tenant_id')) {
                $table->dropIndex('users_tenant_id_index');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropIndex('users_role_index');
            }
        });
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function createIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            $alreadyExists = DB::table('pg_indexes')
                ->where('tablename', $table)
                ->where('indexname', $indexName)
                ->whereRaw('schemaname = current_schema()')
                ->exists();

            if ($alreadyExists) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($columns, $indexName) {
            $tableBlueprint->index($columns, $indexName);
        });
    }
};
