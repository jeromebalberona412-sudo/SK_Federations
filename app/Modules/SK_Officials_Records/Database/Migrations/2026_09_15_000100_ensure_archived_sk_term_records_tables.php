<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure the two completed-term archive tables exist.
 * Additive only — never drops or truncates archive data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archived_sk_official_records')) {
            Schema::create('archived_sk_official_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('official_profile_id')->nullable();
                $table->unsignedBigInteger('official_term_id')->nullable();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('middle_name', 100)->nullable();
                $table->string('suffix', 20)->nullable();
                $table->string('sex', 10)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->unsignedSmallInteger('age')->nullable();
                $table->string('contact_number', 20)->nullable();
                $table->string('position');
                $table->string('municipality')->default('Santa Cruz');
                $table->string('province')->default('Laguna');
                $table->string('region')->default('IV-A CALABARZON');
                $table->string('email')->nullable();
                $table->date('term_start');
                $table->date('term_end');
                $table->string('term_status', 30);
                $table->timestamp('archived_at');
                $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'term_end']);
                $table->index(['barangay_id', 'term_start', 'term_end']);
                $table->unique(['official_term_id']);
            });
        }

        if (! Schema::hasTable('archived_sk_federation_records')) {
            Schema::create('archived_sk_federation_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('official_profile_id')->nullable();
                $table->unsignedBigInteger('official_term_id')->nullable();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('middle_name', 100)->nullable();
                $table->string('suffix', 20)->nullable();
                $table->string('sex', 10)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->unsignedSmallInteger('age')->nullable();
                $table->string('contact_number', 20)->nullable();
                $table->string('position');
                $table->string('municipality')->default('Santa Cruz');
                $table->string('province')->default('Laguna');
                $table->string('region')->default('IV-A CALABARZON');
                $table->string('email')->nullable();
                $table->date('term_start');
                $table->date('term_end');
                $table->string('term_status', 30);
                $table->timestamp('archived_at');
                $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'term_end']);
                $table->unique(['official_term_id']);
            });
        }

        // Additive column only — safe if already present.
        if (
            Schema::hasTable('archived_sk_federation_records')
            && ! Schema::hasColumn('archived_sk_federation_records', 'barangay_id')
        ) {
            Schema::table('archived_sk_federation_records', function (Blueprint $table) {
                $table->foreignId('barangay_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('barangays')
                    ->nullOnDelete();
            });
        }

        if (
            Schema::hasTable('archived_sk_federation_records')
            && ! Schema::hasColumn('archived_sk_federation_records', 'federation_position')
        ) {
            Schema::table('archived_sk_federation_records', function (Blueprint $table) {
                $table->string('federation_position')->nullable()->after('position');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: never drop or delete archive tables/data.
    }
};
