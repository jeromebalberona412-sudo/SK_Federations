<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ChairpersonFederationSyncService
{
    private const CHAIR_POSITIONS = ['Chairperson', 'Chairman'];

    public function syncForUser(User $user, string $position): void
    {
        if (! $this->isChairpersonPosition($position)) {
            if ($user->role === User::ROLE_SK_OFFICIAL && $user->has_federation_access) {
                $user->setHasFederationAccess(false);
            }

            if ($user->officialProfile && $user->officialProfile->federation_position) {
                $user->officialProfile->forceFill(['federation_position' => null])->save();
            }

            return;
        }

        if ($user->role !== User::ROLE_SK_OFFICIAL) {
            return;
        }

        $this->syncPortalAccessFromFederationPosition(
            $user,
            $user->officialProfile?->federation_position,
        );
    }

    public function syncPortalAccessFromFederationPosition(User $user, ?string $federationPosition): void
    {
        if ($user->role !== User::ROLE_SK_OFFICIAL) {
            return;
        }

        $normalized = trim((string) $federationPosition);
        $shouldHaveAccess = in_array($normalized, OfficialProfile::FEDERATION_PORTAL_ACCESS_POSITIONS, true);

        if ((bool) $user->has_federation_access !== $shouldHaveAccess) {
            $user->setHasFederationAccess($shouldHaveAccess);
        }
    }

    public function isChairpersonPosition(string $position): bool
    {
        $normalized = Str::lower(trim($position));
        $normalized = preg_replace('/^(sk\s+)+/i', '', $normalized) ?? $normalized;

        return in_array($normalized, ['chairperson', 'chairman'], true)
            || in_array($position, self::CHAIR_POSITIONS, true);
    }

    /**
     * @param  Builder<\App\Modules\Accounts\Models\OfficialProfile>  $query
     */
    public function applyChairpersonPositionConstraint(Builder $query): void
    {
        $query->where(function (Builder $positionQuery): void {
            $positionQuery->whereIn('position', self::CHAIR_POSITIONS)
                ->orWhereRaw('LOWER(TRIM(position)) IN (?, ?)', ['chairperson', 'chairman'])
                ->orWhereRaw('LOWER(TRIM(position)) IN (?, ?)', ['sk chairperson', 'sk chairman']);
        });
    }

    /**
     * @param  Builder<User>  $query
     */
    public function applyFederationRosterMemberConstraint(Builder $query): void
    {
        $query->where('role', User::ROLE_SK_OFFICIAL)
            ->whereNull('deleted_at')
            ->whereHas('officialProfile', function (Builder $profileQuery): void {
                $this->applyChairpersonPositionConstraint($profileQuery);
            })
            ->whereHas('officialProfile.terms', function (Builder $termQuery): void {
                $termQuery
                    ->where('status', OfficialTerm::STATUS_ACTIVE)
                    ->whereDate('term_end', '>=', now()->startOfDay());
            });
    }

    public function syncFederationAccessForTenant(int $tenantId): void
    {
        User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->whereNull('deleted_at')
            ->with('officialProfile')
            ->each(function (User $user): void {
                $position = (string) ($user->officialProfile?->position ?? '');

                if ($position !== '' && $this->isChairpersonPosition($position)) {
                    $this->syncPortalAccessFromFederationPosition(
                        $user,
                        $user->officialProfile?->federation_position,
                    );

                    return;
                }

                if ($user->has_federation_access) {
                    $user->setHasFederationAccess(false);
                }
            });
    }
}
