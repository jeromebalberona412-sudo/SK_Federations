<?php

use App\Http\Middleware\PreventBackHistory;
use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Authentication\Middleware\EnsureSingleSession;
use App\Modules\Authentication\Middleware\EnsureTrustedDevice;
use App\Modules\Deleted_SK_Officials\Models\DeletedSkOfficial;
use App\Modules\Deleted_SK_Officials\Services\DeletedSkOfficialsService;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureTrustedDevice::class,
        EnsureSingleSession::class,
        PreventBackHistory::class,
    ]);

    if (! extension_loaded('pdo_sqlite') && config('database.default') === 'sqlite') {
        $this->markTestSkipped('pdo_sqlite is required for sqlite tests.');
    }

    if (! Schema::hasTable('tenants') || ! Schema::hasTable('deleted_sk_officials')) {
        $this->markTestSkipped('Seeded application database is required for Deleted SK Officials integrity feature tests.');
    }
});

function dsoIntegrityTenantId(): int
{
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');
    expect($tenantId)->toBeGreaterThan(0);

    return $tenantId;
}

function dsoIntegrityBarangayId(int $tenantId): int
{
    $barangayId = (int) Barangay::query()->where('tenant_id', $tenantId)->value('id');
    expect($barangayId)->toBeGreaterThan(0);

    return $barangayId;
}

function dsoIntegrityAdmin(int $tenantId): User
{
    return User::factory()->create([
        'tenant_id' => $tenantId,
        'role' => User::ROLE_SK_FED,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
    ]);
}

function dsoIntegrityOfficial(int $tenantId, int $barangayId, string $position = 'Kagawad'): User
{
    $official = User::factory()->create([
        'tenant_id' => $tenantId,
        'barangay_id' => $barangayId,
        'role' => User::ROLE_SK_OFFICIAL,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
        'email' => 'dso.integrity.'.uniqid().'@example.com',
        'name' => 'INTEGRITY OFFICIAL',
    ]);

    $profile = OfficialProfile::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $official->id,
        'first_name' => 'INTEGRITY',
        'last_name' => 'OFFICIAL',
        'position' => $position,
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

    return $official->fresh(['officialProfile.terms', 'barangay']);
}

function dsoIntegrityCleanup(User $admin, User $official): void
{
    DB::table('deleted_sk_officials')->where('original_user_id', $official->id)->delete();
    User::withTrashed()->whereKey($official->id)->forceDelete();
    $admin->forceDelete();
}

it('archives without creating duplicate unrecovered snapshots for the same user', function () {
    $tenantId = dsoIntegrityTenantId();
    $barangayId = dsoIntegrityBarangayId($tenantId);
    $admin = dsoIntegrityAdmin($tenantId);
    $official = dsoIntegrityOfficial($tenantId, $barangayId);

    $service = app(DeletedSkOfficialsService::class);
    $first = $service->archiveOfficial($official, $admin, 'first');
    $second = $service->archiveOfficial($official, $admin, 'second');

    expect($second->id)->toBe($first->id)
        ->and($second->deletion_reason)->toBe('second')
        ->and(DeletedSkOfficial::query()
            ->where('original_user_id', $official->id)
            ->whereNull('restored_at')
            ->count())->toBe(1);

    dsoIntegrityCleanup($admin, $official);
});

it('restores the original user id and clears the archive row', function () {
    $tenantId = dsoIntegrityTenantId();
    $barangayId = dsoIntegrityBarangayId($tenantId);
    $admin = dsoIntegrityAdmin($tenantId);
    $official = dsoIntegrityOfficial($tenantId, $barangayId);
    $originalId = (int) $official->id;

    $service = app(DeletedSkOfficialsService::class);
    $archive = $service->archiveOfficial($official, $admin);
    $official->forceFill(['status' => User::STATUS_INACTIVE])->save();
    $official->delete();

    $restored = $service->restoreAccount((int) $archive->id, User::ROLE_SK_OFFICIAL, $admin);

    expect($restored->id)->toBe($originalId)
        ->and($restored->trashed())->toBeFalse()
        ->and($restored->status)->toBe(User::STATUS_ACTIVE)
        ->and(DeletedSkOfficial::query()->whereKey($archive->id)->value('restored_at'))->not->toBeNull();

    dsoIntegrityCleanup($admin, $official);
});

it('rejects restore when the original account is missing', function () {
    $tenantId = dsoIntegrityTenantId();
    $admin = dsoIntegrityAdmin($tenantId);

    $archive = DeletedSkOfficial::query()->create([
        'tenant_id' => $tenantId,
        'original_user_id' => 9_999_999_991,
        'barangay_id' => dsoIntegrityBarangayId($tenantId),
        'barangay_name' => 'Test',
        'email' => 'missing.original.'.uniqid().'@example.com',
        'full_name' => 'MISSING ORIGINAL',
        'position' => 'Kagawad',
        'role' => User::ROLE_SK_OFFICIAL,
        'account_status' => User::STATUS_ACTIVE,
        'deleted_at' => now(),
        'deleted_by' => $admin->id,
        'deleted_by_name' => $admin->name,
        'archived_at' => now(),
    ]);

    $service = app(DeletedSkOfficialsService::class);

    expect(fn () => $service->restoreAccount((int) $archive->id, User::ROLE_SK_OFFICIAL, $admin))
        ->toThrow(ValidationException::class);

    $archive->delete();
    $admin->forceDelete();
});

it('rejects restore when another active account already uses the email', function () {
    $tenantId = dsoIntegrityTenantId();
    $barangayId = dsoIntegrityBarangayId($tenantId);
    $admin = dsoIntegrityAdmin($tenantId);
    $official = dsoIntegrityOfficial($tenantId, $barangayId);
    $email = (string) $official->email;

    $service = app(DeletedSkOfficialsService::class);
    $archive = $service->archiveOfficial($official, $admin);
    $official->forceFill(['status' => User::STATUS_INACTIVE])->save();
    $official->delete();

    // Free the unique email on the soft-deleted row so an active conflict can exist.
    $official->forceFill([
        'email' => 'retired.'.uniqid().'@example.com',
    ])->save();

    $conflict = User::factory()->create([
        'tenant_id' => $tenantId,
        'barangay_id' => $barangayId,
        'role' => User::ROLE_SK_OFFICIAL,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
        'email' => $email,
    ]);

    expect(fn () => $service->restoreAccount((int) $archive->id, User::ROLE_SK_OFFICIAL, $admin))
        ->toThrow(ValidationException::class);

    $conflict->forceDelete();
    dsoIntegrityCleanup($admin, $official);
});
