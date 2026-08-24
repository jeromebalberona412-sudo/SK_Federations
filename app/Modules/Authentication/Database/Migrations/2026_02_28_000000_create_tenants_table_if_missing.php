<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('code', 80)->unique();
                $table->string('municipality')->nullable();
                $table->string('province')->nullable();
                $table->string('region')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $tenantCode = (string) config('sk_fed_auth.tenant_code', 'santa_cruz');

        if ($tenantCode !== '' && Schema::hasTable('tenants')) {
            $exists = DB::table('tenants')->where('code', $tenantCode)->exists();

            if (! $exists) {
                DB::table('tenants')->insert([
                    'name' => 'Santa Cruz Federation',
                    'code' => $tenantCode,
                    'municipality' => 'Santa Cruz',
                    'province' => 'Laguna',
                    'region' => 'IV-A CALABARZON',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::drop('tenants');
        }
    }
};
