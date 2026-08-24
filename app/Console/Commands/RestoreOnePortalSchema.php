<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RestoreOnePortalSchema extends Command
{
    protected $signature = 'oneportal:restore-schema
                            {--force : Required to run against the configured database}
                            {--seed : Insert baseline tenant, barangays, and feature flags}';

    protected $description = 'Rebuild the shared Supabase schema from database_structure/SK_Oneportal.sql';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('This rebuilds the public schema. Re-run with --force to continue.');

            return self::FAILURE;
        }

        $host = (string) config('database.connections.pgsql.host', '');

        if ($host !== '' && str_contains(strtolower($host), 'supabase.com')) {
            if (! $this->option('no-interaction') && ! $this->confirm('You are about to rebuild schema on Supabase ('.$host.'). Continue?', false)) {
                return self::FAILURE;
            }
        }

        $root = dirname(base_path());
        $schemaFile = $root.DIRECTORY_SEPARATOR.'database_structure'.DIRECTORY_SEPARATOR.'SK_Oneportal.sql';
        $patchFile = $root.DIRECTORY_SEPARATOR.'database_structure'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'2026_06_11_000001_sync_sk_oneportal_schema.sql';

        if (! is_file($schemaFile)) {
            $this->error('Missing schema file: '.$schemaFile);

            return self::FAILURE;
        }

        $this->warn('Dropping and recreating public schema...');

        try {
            DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
            DB::statement('CREATE SCHEMA public');
            DB::statement('GRANT ALL ON SCHEMA public TO postgres');
            DB::statement('GRANT ALL ON SCHEMA public TO public');
        } catch (Throwable $exception) {
            $this->error('Failed resetting schema: '.$exception->getMessage());

            return self::FAILURE;
        }

        $schemaSql = $this->prepareSql(file_get_contents($schemaFile) ?: '');
        $patchSql = is_file($patchFile) ? $this->prepareSql(file_get_contents($patchFile) ?: '') : '';

        $this->info('Applying SK_Oneportal.sql ...');

        if (! $this->executeSqlScript($schemaSql)) {
            return self::FAILURE;
        }

        if ($patchSql !== '') {
            $this->info('Applying schema sync patch ...');

            if (! $this->executeSqlScript($patchSql)) {
                return self::FAILURE;
            }
        }

        if ($this->option('seed')) {
            $this->info('Seeding baseline tenant, barangays, and feature flags ...');
            $this->seedBaseline();
        }

        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
        $this->info('Public tables restored: '.count($tables));
        $this->newLine();
        $this->info('Schema restore finished.');
        $this->warn('User accounts and historical rows are NOT restored by this command. Use Supabase backup/PITR for deleted data.');

        return self::SUCCESS;
    }

    protected function prepareSql(string $raw): string
    {
        $raw = preg_replace('/^\s*Database\s*=.*$/mi', '', $raw) ?? $raw;
        $raw = preg_replace('/^\s*Last Updated\s*=.*$/mi', '', $raw) ?? $raw;
        $raw = str_replace('///', '--', $raw);
        $raw = preg_replace('/\sTABLESPACE pg_default/i', '', $raw) ?? $raw;

        $raw = preg_replace(
            '/create table public\.calendar_notes \([\s\S]*?\);\s*(?=create index IF not exists calendar_notes_barangay_id_note_date_index on public\.calendar_notes)/i',
            '',
            $raw,
            1,
        ) ?? $raw;

        $raw = preg_replace(
            '/CREATE TABLE IF NOT EXISTS committees \([\s\S]*?\);\s*/i',
            '',
            $raw,
            1,
        ) ?? $raw;

        return trim($raw);
    }

    protected function executeSqlScript(string $sql): bool
    {
        if ($sql === '') {
            return true;
        }

        try {
            DB::connection()->getPdo()->exec($sql);
        } catch (Throwable $exception) {
            $this->error('SQL execution failed: '.$exception->getMessage());

            return false;
        }

        return true;
    }

    protected function seedBaseline(): void
    {
        $now = now();

        $tenantId = app(\App\Modules\Authentication\Services\SkFedTenantResolver::class)->tenantId();

        if ($tenantId === null) {
            $tenantId = app(\App\Modules\Authentication\Services\SkFedTenantResolver::class)->ensureTenantExists();
        }

        if ($tenantId === null) {
            $tenantCode = (string) config('sk_fed_auth.tenant_code', 'santa_cruz');
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => 'Santa Cruz Federation',
                'code' => $tenantCode !== '' ? $tenantCode : 'santa_cruz',
                'municipality' => 'Santa Cruz',
                'province' => 'Laguna',
                'region' => 'IV-A CALABARZON',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $barangays = [
            'Alipit', 'Bagumbayan', 'Bubukal', 'Calios', 'Duhat', 'Gatid', 'Jasaan', 'Labuin',
            'Malinao', 'Oogong', 'Pagsawitan', 'Palasan', 'Patimbao', 'Poblacion I', 'Poblacion II',
            'Poblacion III', 'Poblacion IV', 'Poblacion V', 'San Jose', 'San Juan', 'San Pablo Norte',
            'San Pablo Sur', 'Santisima Cruz', 'Santo Angel Central', 'Santo Angel Norte', 'Santo Angel Sur',
        ];

        foreach ($barangays as $name) {
            $exists = DB::table('barangays')
                ->where('tenant_id', $tenantId)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('barangays')->insert([
                'tenant_id' => $tenantId,
                'name' => $name,
                'municipality' => 'Santa Cruz',
                'province' => 'Laguna',
                'region' => 'IV-A CALABARZON',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $fedFlags = [
            ['features.device_verification', 'Enable trusted-device challenge flow for SK FED.', true],
            ['features.login_alert_notifications', 'Send login alerts for unusual sign-ins.', true],
            ['features.suspicious_login_detection', 'Detect suspicious login patterns.', true],
        ];

        foreach ($fedFlags as [$key, $description, $enabled]) {
            if (DB::table('sk_fed_feature_flags')->where('flag_key', $key)->exists()) {
                continue;
            }

            DB::table('sk_fed_feature_flags')->insert([
                'flag_key' => $key,
                'enabled' => $enabled,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
