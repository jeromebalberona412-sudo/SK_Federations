<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table): void {
                if (! Schema::hasColumn('conversations', 'name')) {
                    $table->string('name', 120)->nullable()->after('conversation_type');
                }
                if (! Schema::hasColumn('conversations', 'default_group_type')) {
                    $table->string('default_group_type', 40)->nullable()->after('name');
                }
                if (! Schema::hasColumn('conversations', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('default_group_type');
                }
                if (! Schema::hasColumn('conversations', 'max_members')) {
                    $table->unsignedInteger('max_members')->nullable()->after('tenant_id');
                }
            });

            if (Schema::hasColumn('conversations', 'default_group_type')
                && Schema::hasColumn('conversations', 'tenant_id')
                && ! $this->hasIndex('conversations', 'conversations_tenant_default_group_unique')
            ) {
                Schema::table('conversations', function (Blueprint $table): void {
                    $table->unique(
                        ['tenant_id', 'default_group_type'],
                        'conversations_tenant_default_group_unique'
                    );
                });
            }
        }

        if (Schema::hasTable('conversation_participants')
            && ! Schema::hasColumn('conversation_participants', 'participant_role')
        ) {
            Schema::table('conversation_participants', function (Blueprint $table): void {
                $table->string('participant_role', 20)->default('member')->after('user_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversations')) {
            if ($this->hasIndex('conversations', 'conversations_tenant_default_group_unique')) {
                Schema::table('conversations', function (Blueprint $table): void {
                    $table->dropUnique('conversations_tenant_default_group_unique');
                });
            }

            Schema::table('conversations', function (Blueprint $table): void {
                $drop = [];
                foreach (['max_members', 'tenant_id', 'default_group_type', 'name'] as $column) {
                    if (Schema::hasColumn('conversations', $column)) {
                        $drop[] = $column;
                    }
                }
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasTable('conversation_participants')
            && Schema::hasColumn('conversation_participants', 'participant_role')
        ) {
            Schema::table('conversation_participants', function (Blueprint $table): void {
                $table->dropColumn('participant_role');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $database = $connection->getDatabaseName();
            $driver = $connection->getDriverName();

            if ($driver === 'pgsql') {
                $rows = DB::select(
                    'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                    [$table, $indexName]
                );

                return $rows !== [];
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                $rows = DB::select(
                    'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                    [$database, $table, $indexName]
                );

                return $rows !== [];
            }

            if ($driver === 'sqlite') {
                $rows = DB::select("PRAGMA index_list('{$table}')");
                foreach ($rows as $row) {
                    $name = (string) ($row->name ?? '');
                    if ($name === $indexName) {
                        return true;
                    }
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
};
