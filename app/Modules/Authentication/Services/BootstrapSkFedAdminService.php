<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BootstrapSkFedAdminService
{
    public const BOOTSTRAP_EMAIL = 'skoneportal@gmail.com';

    public const DEFAULT_PASSWORD = '@Jerome123456';

    public static function bootstrapEmailNormalized(): string
    {
        return Str::lower(self::BOOTSTRAP_EMAIL);
    }

    public function isBootstrapAdmin(User $user): bool
    {
        return Str::lower((string) $user->email) === self::bootstrapEmailNormalized();
    }

    public function __construct(
        protected SkFedTenantResolver $tenantResolver,
    ) {}

    public function ensure(bool $resetPassword = false): User
    {
        $tenantId = $this->tenantResolver->ensureTenantExists();
        $email = Str::lower(self::BOOTSTRAP_EMAIL);

        $user = User::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user?->trashed()) {
            $user->restore();
        }

        $attributes = [
            'name' => 'SK Federation Administrator',
            'email' => $email,
            'role' => User::ROLE_SK_FED,
            'status' => User::STATUS_ACTIVE,
            'tenant_id' => $tenantId,
            'email_verified_at' => now(),
        ];

        if ($resetPassword || $user === null) {
            $attributes['password'] = self::DEFAULT_PASSWORD;
        }

        if ($user === null) {
            $user = User::query()->create($attributes);
        } else {
            $user->forceFill($attributes)->save();
        }

        if (Schema::hasColumn('users', 'must_change_password')) {
            DB::table('users')
                ->where('id', $user->getKey())
                ->update(['must_change_password' => DB::raw('true')]);
        }

        $user = $user->fresh() ?? $user;

        $this->ensureOfficialProfile($user, $tenantId);

        return $user->fresh() ?? $user;
    }

    public function normalizeUserIfBootstrap(User $user): User
    {
        if (Str::lower((string) $user->email) !== Str::lower(self::BOOTSTRAP_EMAIL)) {
            return $user;
        }

        $tenantId = $this->tenantResolver->tenantId() ?? $this->tenantResolver->ensureTenantExists();
        $updates = [];

        if ($this->usersTableHasColumn('role') && ! $user->hasRole(User::ROLE_SK_FED)) {
            $updates['role'] = User::ROLE_SK_FED;
        }

        if ($this->usersTableHasColumn('tenant_id') && (int) ($user->tenant_id ?? 0) !== $tenantId) {
            $updates['tenant_id'] = $tenantId;
        }

        if ($this->usersTableHasColumn('status') && (string) $user->status !== User::STATUS_ACTIVE) {
            $updates['status'] = User::STATUS_ACTIVE;
        }

        if ($this->usersTableHasColumn('email_verified_at') && $user->email_verified_at === null) {
            $updates['email_verified_at'] = now();
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();

            return $user->fresh() ?? $user;
        }

        return $user;
    }

    protected function ensureOfficialProfile(User $user, int $tenantId): void
    {
        if (! Schema::hasTable('official_profiles')) {
            return;
        }

        $exists = DB::table('official_profiles')->where('user_id', $user->getKey())->exists();
        $now = now();

        if ($exists) {
            DB::table('official_profiles')->where('user_id', $user->getKey())->update([
                'first_name' => 'Admin',
                'middle_name' => 'Admin',
                'position' => 'Admin',
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('official_profiles')->insert([
            'user_id' => $user->getKey(),
            'first_name' => 'Admin',
            'middle_name' => 'Admin',
            'last_name' => 'Administrator',
            'position' => 'Admin',
            'municipality' => 'Santa Cruz',
            'province' => 'Laguna',
            'region' => 'IV-A CALABARZON',
            'tenant_id' => $tenantId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (! Schema::hasTable('official_terms')) {
            return;
        }

        $profileId = DB::table('official_profiles')->where('user_id', $user->getKey())->value('id');

        if ($profileId === null) {
            return;
        }

        $termExists = DB::table('official_terms')->where('official_profile_id', $profileId)->exists();

        if ($termExists) {
            return;
        }

        $termStart = now()->startOfYear()->toDateString();
        $termEnd = now()->startOfYear()->addYears(3)->subDay()->toDateString();

        DB::table('official_terms')->insert([
            'official_profile_id' => $profileId,
            'term_start' => $termStart,
            'term_end' => $termEnd,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function usersTableHasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn('users', $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
