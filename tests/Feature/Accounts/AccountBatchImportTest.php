<?php

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Services\BatchAccountImportService;
use App\Modules\Accounts\Services\ChairpersonFederationSyncService;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('validates batch rows using simplified officials template rules', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    expect($tenantId)->toBeGreaterThan(0);

    $service = new BatchAccountImportService($tenantId);

    $errors = $service->validateRows([
        [
            'first_name' => 'Jo',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'barangay' => 'San Roque',
            'position' => 'Chairperson',
            'sex' => 'Male',
            'date_of_birth' => '2000-01-01',
            'contact_number' => '09171234567',
            'term_start' => '2023-11-30',
            'term_end' => '2026-11-30',
        ],
    ], User::ROLE_SK_OFFICIAL);

    expect($errors)->not->toBeEmpty();
});

it('normalizes valid officials batch rows with strict template validation', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    $service = new BatchAccountImportService($tenantId);

    $row = $service->normalizeAccountRow([
        'first_name' => 'JUAN',
        'middle_name' => '',
        'last_name' => 'DELA CRUZ',
        'suffix' => 'Jr.',
        'sex' => 'Male',
        'date_of_birth' => '2005-01-15',
        'contact_number' => '09171234567',
        'email' => 'juan.batch.'.uniqid().'@gmail.com',
        'barangay' => 'San Roque',
        'position' => 'Chairperson',
        'term_start' => '2023-11-30',
        'term_end' => '2026-11-30',
    ], User::ROLE_SK_OFFICIAL, true);

    expect($row['email'])->toContain('@gmail.com')
        ->and($row['position'])->toBe('Chairperson')
        ->and($row['middle_name'])->toBeNull()
        ->and($row['status'])->toBe(User::STATUS_ACTIVE);
});

it('syncs federation access for chairperson officials only', function () {
    if (! Schema::hasColumn('users', 'has_federation_access')) {
        $this->markTestSkipped('has_federation_access column is not migrated yet.');
    }

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    $barangayId = (int) Barangay::query()->where('tenant_id', $tenantId)->value('id');

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
        'barangay_id' => $barangayId ?: null,
        'role' => User::ROLE_SK_OFFICIAL,
        'has_federation_access' => false,
    ]);

    $profile = OfficialProfile::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'first_name' => 'JUAN',
        'last_name' => 'DELA CRUZ',
        'position' => 'Chairperson',
        'municipality' => 'Santa Cruz',
        'province' => 'Laguna',
        'region' => 'IV-A CALABARZON',
    ]);

    OfficialTerm::query()->create([
        'official_profile_id' => $profile->id,
        'term_start' => now()->startOfYear()->toDateString(),
        'term_end' => now()->addYears(3)->toDateString(),
        'status' => OfficialTerm::STATUS_ACTIVE,
    ]);

    $sync = new ChairpersonFederationSyncService;
    $sync->syncForUser($user->fresh(), 'Chairperson');

    expect($user->fresh()->has_federation_access)->toBeTrue();

    $user->forceDelete();
    $profile->delete();
});

it('rejects duplicate emails during batch validation', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    $existing = User::query()->where('tenant_id', $tenantId)->first();
    expect($existing)->not->toBeNull();

    $service = new BatchAccountImportService($tenantId);
    $errors = $service->validateRows([
        [
            'first_name' => 'MARIA',
            'last_name' => 'REYES',
            'email' => $existing->email,
            'barangay' => 'San Roque',
            'position' => 'Secretary',
            'sex' => 'Female',
            'date_of_birth' => '2005-05-10',
            'contact_number' => '09179876543',
            'term_start' => '2023-11-30',
            'term_end' => '2026-11-30',
        ],
    ], User::ROLE_SK_OFFICIAL);

    expect(collect($errors)->pluck('error')->first())->toContain('already registered');
});

it('parses excel serials, us dates, and numeric age without dropping valid values', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    $service = new BatchAccountImportService($tenantId);
    $excelEpoch = Carbon\Carbon::create(1899, 12, 30)->startOfDay();

    $row = $service->normalizeAccountRow([
        'first_name' => 'PAULA',
        'middle_name' => 'AGRAVANTE',
        'last_name' => 'TALABIS',
        'suffix' => 'None',
        'sex' => 'Female',
        'date_of_birth' => (int) $excelEpoch->diffInDays(Carbon\Carbon::create(2008, 7, 2)->startOfDay()),
        'age' => 18,
        'contact_number' => '09568103267',
        'email' => '  paulatalabis5@gmail.com  ',
        'barangay' => 'San Roque',
        'position' => 'SK Treasurer',
        'region' => 'IV-A CALABARZON',
        'province' => 'Laguna',
        'municipality' => 'Santa Cruz',
        'term_start' => (int) $excelEpoch->diffInDays(Carbon\Carbon::create(2023, 11, 30)->startOfDay()),
        'term_end' => '11/30/2026',
    ], User::ROLE_SK_OFFICIAL, true);

    expect($row['date_of_birth'])->toBe('2008-07-02')
        ->and($row['age'])->toBe(18)
        ->and($row['term_start'])->toBe('2023-11-30')
        ->and($row['term_end'])->toBe('2026-11-30')
        ->and($row['email'])->toBe('paulatalabis5@gmail.com')
        ->and($row['position'])->toBe('Treasurer');
});

it('parses datetime objects and mm/dd/yyyy strings for officials batch dates', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    $service = new BatchAccountImportService($tenantId);

    $row = $service->normalizeAccountRow([
        'first_name' => 'MIGUEL',
        'middle_name' => 'SANTOS',
        'last_name' => 'REYES',
        'suffix' => 'None',
        'sex' => 'Male',
        'date_of_birth' => new DateTimeImmutable('2007-03-15 00:00:00'),
        'age' => '19',
        'contact_number' => '09171234567',
        'email' => 'miguelreyesbatch@gmail.com',
        'barangay' => 'San Roque',
        'position' => 'Secretary',
        'region' => 'IV-A CALABARZON',
        'province' => 'Laguna',
        'municipality' => 'Santa Cruz',
        'term_start' => '11/30/2023',
        'term_end' => new DateTimeImmutable('2026-11-30'),
    ], User::ROLE_SK_OFFICIAL, true);

    expect($row['date_of_birth'])->toBe('2007-03-15')
        ->and($row['age'])->toBe(19)
        ->and($row['term_start'])->toBe('2023-11-30')
        ->and($row['term_end'])->toBe('2026-11-30');
});

it('reports an age mismatch instead of dropping the uploaded age', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    $service = new BatchAccountImportService($tenantId);

    $errors = $service->validateRows([
        [
            'first_name' => 'ANDREA',
            'middle_name' => 'CRUZ',
            'last_name' => 'MENDOZA',
            'suffix' => 'Jr.',
            'sex' => 'Female',
            'date_of_birth' => '07/02/2008',
            'age' => 21,
            'contact_number' => '09179876543',
            'email' => 'andreamendozabatch@gmail.com',
            'barangay' => 'San Roque',
            'position' => 'Kagawad',
            'region' => 'IV-A CALABARZON',
            'province' => 'Laguna',
            'municipality' => 'Santa Cruz',
            'term_start' => '11/30/2023',
            'term_end' => '11/30/2026',
        ],
    ], User::ROLE_SK_OFFICIAL);

    expect($errors)->not->toBeEmpty()
        ->and(collect($errors)->pluck('error')->implode(' '))->toContain('does not match birthdate');
});
