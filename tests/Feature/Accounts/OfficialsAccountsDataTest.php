<?php

use App\Http\Middleware\PreventBackHistory;
use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Services\AccountService;
use App\Modules\Deleted_SK_Officials\Services\DeletedSkOfficialsService;
use App\Modules\Authentication\Middleware\EnsureSingleSession;
use App\Modules\Authentication\Middleware\EnsureTrustedDevice;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureTrustedDevice::class,
        EnsureSingleSession::class,
        PreventBackHistory::class,
    ]);
});

it('does not list soft-deleted officials in the accounts officials data endpoint', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    expect($tenantId)->toBeGreaterThan(0);

    $barangayId = (int) Barangay::query()->where('tenant_id', $tenantId)->value('id');
    expect($barangayId)->toBeGreaterThan(0);

    $admin = User::factory()->create([
        'tenant_id' => $tenantId,
        'role' => User::ROLE_SK_FED,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
    ]);

    $activeOfficial = User::factory()->create([
        'tenant_id' => $tenantId,
        'barangay_id' => $barangayId,
        'role' => User::ROLE_SK_OFFICIAL,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
        'email' => 'active.official.'.uniqid().'@example.com',
    ]);

    $activeProfile = OfficialProfile::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $activeOfficial->id,
        'first_name' => 'ACTIVE',
        'last_name' => 'OFFICIAL',
        'position' => 'Secretary',
        'municipality' => 'Santa Cruz',
        'province' => 'Laguna',
        'region' => 'IV-A CALABARZON',
    ]);

    OfficialTerm::query()->create([
        'official_profile_id' => $activeProfile->id,
        'term_start' => now()->subMonth()->toDateString(),
        'term_end' => now()->addYears(2)->toDateString(),
        'status' => OfficialTerm::STATUS_ACTIVE,
    ]);

    $deletedOfficial = User::factory()->create([
        'tenant_id' => $tenantId,
        'barangay_id' => $barangayId,
        'role' => User::ROLE_SK_OFFICIAL,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
        'email' => 'deleted.official.'.uniqid().'@example.com',
    ]);

    $deletedProfile = OfficialProfile::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $deletedOfficial->id,
        'first_name' => 'DELETED',
        'last_name' => 'OFFICIAL',
        'position' => 'Treasurer',
        'municipality' => 'Santa Cruz',
        'province' => 'Laguna',
        'region' => 'IV-A CALABARZON',
    ]);

    OfficialTerm::query()->create([
        'official_profile_id' => $deletedProfile->id,
        'term_start' => now()->subMonth()->toDateString(),
        'term_end' => now()->addYears(2)->toDateString(),
        'status' => OfficialTerm::STATUS_ACTIVE,
    ]);

    app(AccountService::class)->deactivate($deletedOfficial, $admin);

    expect($deletedOfficial->fresh())->toBeNull()
        ->and(User::withTrashed()->find($deletedOfficial->id)?->trashed())->toBeTrue();

    $response = $this->actingAs($admin)
        ->getJson(route('accounts.officials.data'));

    $response->assertOk()
        ->assertJsonPath('success', true);

    $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();

    expect($ids)->toContain($activeOfficial->id)
        ->and($ids)->not->toContain($deletedOfficial->id);

    $activeOfficial->forceDelete();
    User::withTrashed()->whereKey($deletedOfficial->id)->forceDelete();
    $admin->forceDelete();
});

it('moves a deleted official out of accounts data and into the deleted archive', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    $barangayId = (int) Barangay::query()->where('tenant_id', $tenantId)->value('id');

    $admin = User::factory()->create([
        'tenant_id' => $tenantId,
        'role' => User::ROLE_SK_FED,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
    ]);

    $official = User::factory()->create([
        'tenant_id' => $tenantId,
        'barangay_id' => $barangayId,
        'role' => User::ROLE_SK_OFFICIAL,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
        'email' => 'archive.move.'.uniqid().'@example.com',
    ]);

    $profile = OfficialProfile::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $official->id,
        'first_name' => 'ARCHIVE',
        'last_name' => 'MOVE',
        'position' => 'Auditor',
        'municipality' => 'Santa Cruz',
        'province' => 'Laguna',
        'region' => 'IV-A CALABARZON',
    ]);

    OfficialTerm::query()->create([
        'official_profile_id' => $profile->id,
        'term_start' => now()->subMonth()->toDateString(),
        'term_end' => now()->addYears(2)->toDateString(),
        'status' => OfficialTerm::STATUS_ACTIVE,
    ]);

    $this->actingAs($admin)
        ->postJson(route('accounts.deactivate', $official))
        ->assertOk()
        ->assertJsonPath('success', true);

    $accountsResponse = $this->actingAs($admin)
        ->getJson(route('accounts.officials.data'));

    $accountIds = collect($accountsResponse->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect($accountIds)->not->toContain($official->id);

    $archiveResponse = $this->actingAs($admin)
        ->getJson(route('archived.deleted-sk-officials.data'));

    $archiveResponse->assertOk();
    $archiveRows = collect($archiveResponse->json('data'));
    expect($archiveRows->pluck('original_user_id')->map(fn ($id) => (int) $id)->all())->toContain($official->id);

    $archivedRow = $archiveRows->firstWhere('original_user_id', $official->id);
    expect($archivedRow['email'])->toBe($official->email)
        ->and($archivedRow['firstName'])->toBe('ARCHIVE')
        ->and($archivedRow['lastName'])->toBe('MOVE');

    expect(DB::table('deleted_sk_officials')
        ->where('original_user_id', $official->id)
        ->whereNull('restored_at')
        ->exists())->toBeTrue();

    User::withTrashed()->whereKey($official->id)->forceDelete();
    DB::table('deleted_sk_officials')->where('original_user_id', $official->id)->delete();
    $admin->forceDelete();
});

it('keeps delete responses on the SK Officials list without archive redirect', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    $barangayId = (int) Barangay::query()->where('tenant_id', $tenantId)->value('id');

    $admin = User::factory()->create([
        'tenant_id' => $tenantId,
        'role' => User::ROLE_SK_FED,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
    ]);

    $official = User::factory()->create([
        'tenant_id' => $tenantId,
        'barangay_id' => $barangayId,
        'role' => User::ROLE_SK_OFFICIAL,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
        'email' => 'archive.redirect.'.uniqid().'@example.com',
    ]);

    OfficialProfile::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $official->id,
        'first_name' => 'REDIRECT',
        'last_name' => 'OFFICIAL',
        'position' => 'Secretary',
        'municipality' => 'Santa Cruz',
        'province' => 'Laguna',
        'region' => 'IV-A CALABARZON',
    ]);

    $this->actingAs($admin)
        ->postJson(route('accounts.deactivate', $official))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'SK Official moved to Deleted SK Officials.')
        ->assertJsonPath('deleted_id', $official->id)
        ->assertJsonMissing(['redirect']);

    expect(User::withTrashed()->whereKey($official->id)->first()?->trashed())->toBeTrue();
    expect(DB::table('deleted_sk_officials')->where('original_user_id', $official->id)->whereNull('restored_at')->exists())->toBeTrue();

    User::withTrashed()->whereKey($official->id)->forceDelete();
    DB::table('deleted_sk_officials')->where('original_user_id', $official->id)->delete();
    $admin->forceDelete();
});

it('does not permanently purge archived sk officials after 30 days', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    $barangayId = (int) Barangay::query()->where('tenant_id', $tenantId)->value('id');

    $admin = User::factory()->create([
        'tenant_id' => $tenantId,
        'role' => User::ROLE_SK_FED,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
    ]);

    $official = User::factory()->create([
        'tenant_id' => $tenantId,
        'barangay_id' => $barangayId,
        'role' => User::ROLE_SK_OFFICIAL,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
        'email' => 'archive.purge.'.uniqid().'@example.com',
    ]);

    $profile = OfficialProfile::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $official->id,
        'first_name' => 'PURGE',
        'last_name' => 'OFFICIAL',
        'position' => 'Treasurer',
        'municipality' => 'Santa Cruz',
        'province' => 'Laguna',
        'region' => 'IV-A CALABARZON',
    ]);

    OfficialTerm::query()->create([
        'official_profile_id' => $profile->id,
        'term_start' => now()->subMonth()->toDateString(),
        'term_end' => now()->addYears(2)->toDateString(),
        'status' => OfficialTerm::STATUS_ACTIVE,
    ]);

    app(AccountService::class)->deactivate($official, $admin);

    User::withTrashed()->whereKey($official->id)->update([
        'deleted_at' => now()->subDays(31),
    ]);
    DB::table('deleted_sk_officials')
        ->where('original_user_id', $official->id)
        ->update([
            'deleted_at' => now()->subDays(31),
            'archived_at' => now()->subDays(31),
        ]);

    $purged = app(DeletedSkOfficialsService::class)
        ->purgeExpiredDeletedOfficials($tenantId);

    expect($purged)->toBe(0)
        ->and(User::withTrashed()->find($official->id))->not->toBeNull()
        ->and(DB::table('deleted_sk_officials')->where('original_user_id', $official->id)->whereNull('restored_at')->exists())->toBeTrue();

    User::withTrashed()->whereKey($official->id)->forceDelete();
    DB::table('deleted_sk_officials')->where('original_user_id', $official->id)->delete();
    $admin->forceDelete();
});
