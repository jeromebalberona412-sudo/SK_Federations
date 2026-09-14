<?php

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Shared\Models\User;
use App\Modules\SK_Federation_Records\Models\ArchivedSkFederationRecord;
use App\Modules\SK_Officials_Records\Models\ArchivedSkOfficialRecord;
use App\Modules\SK_Officials_Records\Services\ArchivedTermRecordsBackfillService;
use App\Modules\SK_Officials_Records\Services\SkOfficialsRecordsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! extension_loaded('pdo_sqlite') && config('database.default') === 'sqlite') {
        $this->markTestSkipped('pdo_sqlite is required for sqlite tests.');
    }

    if (! Schema::hasTable('archived_sk_official_records')
        || ! Schema::hasTable('archived_sk_federation_records')
        || ! Schema::hasTable('official_terms')
        || ! Schema::hasTable('tenants')
    ) {
        $this->markTestSkipped('Archive tables are required for previous-term records tests.');
    }
});

function archiveTermTenantId(): int
{
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    expect($tenantId)->toBeGreaterThan(0);

    return $tenantId;
}

function archiveTermBarangayId(int $tenantId): int
{
    $barangayId = (int) Barangay::query()->where('tenant_id', $tenantId)->value('id');
    expect($barangayId)->toBeGreaterThan(0);

    return $barangayId;
}

function archiveTermCleanup(User $user, OfficialTerm $term): void
{
    ArchivedSkOfficialRecord::query()->where('official_term_id', $term->id)->delete();
    ArchivedSkFederationRecord::query()->where('official_term_id', $term->id)->delete();
    OfficialTerm::query()->whereKey($term->id)->delete();
    OfficialProfile::query()->where('user_id', $user->id)->delete();
    User::withTrashed()->whereKey($user->id)->forceDelete();
}

it('backfills ended terms once and excludes active current terms from records list', function () {
    $tenantId = archiveTermTenantId();
    $barangayId = archiveTermBarangayId($tenantId);

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
        'barangay_id' => $barangayId,
        'role' => User::ROLE_SK_OFFICIAL,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
        'email' => 'archive.term.'.uniqid().'@example.com',
        'name' => 'ARCHIVE TERM OFFICIAL',
    ]);

    $profile = OfficialProfile::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'first_name' => 'ARCHIVE',
        'last_name' => 'TERM',
        'position' => 'Chairperson',
        'municipality' => 'Santa Cruz',
        'province' => 'Laguna',
        'region' => 'IV-A CALABARZON',
    ]);

    $ended = OfficialTerm::query()->create([
        'official_profile_id' => $profile->id,
        'term_start' => now()->subYears(3)->toDateString(),
        'term_end' => now()->subDay()->toDateString(),
        'status' => OfficialTerm::STATUS_ACTIVE,
    ]);

    $active = OfficialTerm::query()->create([
        'official_profile_id' => $profile->id,
        'term_start' => now()->toDateString(),
        'term_end' => now()->addYears(3)->toDateString(),
        'status' => OfficialTerm::STATUS_ACTIVE,
    ]);

    $service = app(ArchivedTermRecordsBackfillService::class);

    $first = $service->backfill($tenantId);
    expect($first['officials_inserted'])->toBeGreaterThanOrEqual(1);

    $second = $service->backfill($tenantId);
    expect($second['officials_inserted'])->toBe(0)
        ->and($second['federation_inserted'])->toBe(0);

    expect(ArchivedSkOfficialRecord::query()->where('official_term_id', $ended->id)->count())->toBe(1);
    expect(ArchivedSkOfficialRecord::query()->where('official_term_id', $active->id)->count())->toBe(0);

    $payload = app(SkOfficialsRecordsService::class)->listRecords($tenantId, Request::create('/', 'GET'));
    $ids = collect($payload['data'])->pluck('official_term_id')->all();

    expect($ids)->toContain($ended->id)
        ->and($ids)->not->toContain($active->id);

    OfficialTerm::query()->whereKey($active->id)->delete();
    archiveTermCleanup($user, $ended);
});
