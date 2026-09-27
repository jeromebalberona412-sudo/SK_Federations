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

        // Existing install used a PostgreSQL VIEW with this name. Replace it with a real archive table.
        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                DO $$
                BEGIN
                    IF EXISTS (
                        SELECT 1
                        FROM pg_class c
                        JOIN pg_namespace n ON n.oid = c.relnamespace
                        WHERE n.nspname = current_schema()
                          AND c.relname = 'deleted_sk_officials'
                          AND c.relkind = 'v'
                    ) THEN
                        EXECUTE 'DROP VIEW deleted_sk_officials CASCADE';
                    END IF;
                END $$;
            SQL);
        } elseif (! Schema::hasTable('deleted_sk_officials')) {
            Schema::dropIfExists('deleted_sk_officials');
        }

        if (! Schema::hasTable('deleted_sk_officials')) {
        Schema::create('deleted_sk_officials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('original_user_id')->nullable()->index();
            $table->unsignedBigInteger('barangay_id')->nullable()->index();
            $table->string('barangay_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('full_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix', 30)->nullable();
            $table->string('contact_number', 40)->nullable();
            $table->string('position')->nullable();
            $table->string('federation_position')->nullable();
            $table->string('role', 40)->default('sk_official')->index();
            $table->string('account_status', 40)->nullable();
            $table->string('sex', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->string('region')->nullable();
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            $table->string('term_status', 40)->nullable();
            $table->string('term_label', 40)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('original_updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->string('deleted_by_name')->nullable();
            $table->string('deletion_reason')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamp('restored_at')->nullable()->index();
            $table->timestamps();

            $table->index(['tenant_id', 'deleted_at']);
            $table->index(['tenant_id', 'restored_at']);
            $table->index(['original_user_id', 'restored_at']);
        });
        }

        // Preserve already soft-deleted SK Officials into the archive table.
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'deleted_at')) {
            $rows = DB::table('users as u')
                ->leftJoin('official_profiles as op', 'op.user_id', '=', 'u.id')
                ->leftJoin('barangays as b', 'b.id', '=', 'u.barangay_id')
                ->whereNotNull('u.deleted_at')
                ->where('u.role', 'sk_official')
                ->select([
                    'u.id as original_user_id',
                    'u.tenant_id',
                    'u.barangay_id',
                    'b.name as barangay_name',
                    'u.email',
                    'u.name as full_name',
                    'op.first_name',
                    'op.middle_name',
                    'op.last_name',
                    'op.suffix',
                    'op.contact_number',
                    'op.position',
                    'op.federation_position',
                    'u.role',
                    'u.status as account_status',
                    'op.sex',
                    'op.date_of_birth',
                    'op.age',
                    'op.municipality',
                    'op.province',
                    'op.region',
                    'u.email_verified_at',
                    'u.created_at as original_created_at',
                    'u.updated_at as original_updated_at',
                    'u.deleted_at',
                ])
                ->get();

            $now = now();
            foreach ($rows as $row) {
                $exists = DB::table('deleted_sk_officials')
                    ->where('original_user_id', $row->original_user_id)
                    ->whereNull('restored_at')
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('deleted_sk_officials')->insert([
                    'tenant_id' => $row->tenant_id,
                    'original_user_id' => $row->original_user_id,
                    'barangay_id' => $row->barangay_id,
                    'barangay_name' => $row->barangay_name,
                    'email' => $row->email,
                    'full_name' => $row->full_name,
                    'first_name' => $row->first_name,
                    'middle_name' => $row->middle_name,
                    'last_name' => $row->last_name,
                    'suffix' => $row->suffix,
                    'contact_number' => $row->contact_number,
                    'position' => $row->position,
                    'federation_position' => $row->federation_position,
                    'role' => $row->role ?: 'sk_official',
                    'account_status' => $row->account_status,
                    'sex' => $row->sex,
                    'date_of_birth' => $row->date_of_birth,
                    'age' => $row->age,
                    'municipality' => $row->municipality,
                    'province' => $row->province,
                    'region' => $row->region,
                    'email_verified_at' => $row->email_verified_at,
                    'original_created_at' => $row->original_created_at,
                    'original_updated_at' => $row->original_updated_at,
                    'deleted_at' => $row->deleted_at,
                    'archived_at' => $row->deleted_at ?: $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_sk_officials');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }

        // Recreate the previous view for rollback compatibility.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW deleted_sk_officials AS
            SELECT
                u.id,
                u.tenant_id,
                u.barangay_id,
                u.name,
                u.email,
                u.status,
                u.email_verified_at,
                u.deleted_at,
                op.first_name,
                op.middle_name,
                op.last_name,
                op.suffix,
                op.sex,
                op.date_of_birth,
                op.age,
                op.contact_number,
                op.position,
                op.municipality,
                op.province,
                op.region
            FROM users u
            LEFT JOIN official_profiles op ON op.user_id = u.id
            WHERE u.deleted_at IS NOT NULL
              AND u.role = 'sk_official'
        SQL);
    }
};
