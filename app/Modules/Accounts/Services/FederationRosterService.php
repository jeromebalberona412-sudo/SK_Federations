<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Notifications\FederationPositionAssignedNotification;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FederationRosterService
{
    public function __construct(
        private readonly ChairpersonFederationSyncService $chairpersonFederationSyncService,
    ) {
    }

    public function isChairPosition(string $position): bool
    {
        return $this->chairpersonFederationSyncService->isChairpersonPosition($position);
    }

    /**
     * @return Builder<User>
     */
    public function federationRosterQuery(int $tenantId): Builder
    {
        $query = User::query()
            ->with(['barangay', 'officialProfile.latestTerm'])
            ->where('tenant_id', $tenantId);

        $this->chairpersonFederationSyncService->applyFederationRosterMemberConstraint($query);

        return $query;
    }

    public function syncFederationRosterAccess(int $tenantId): void
    {
        $this->chairpersonFederationSyncService->syncFederationAccessForTenant($tenantId);
    }

    /**
     * @return list<string>
     */
    public function takenFederationPositions(int $tenantId): array
    {
        $rosterUserIds = $this->federationRosterQuery($tenantId)->pluck('id');

        if ($rosterUserIds->isEmpty()) {
            return [];
        }

        return OfficialProfile::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('federation_position')
            ->whereIn('user_id', $rosterUserIds)
            ->pluck('federation_position')
            ->all();
    }

    public function assertSingleChairPerBarangay(
        int $tenantId,
        int $barangayId,
        string $position,
        ?int $ignoreUserId = null,
    ): void {
        if (! $this->isChairPosition($position)) {
            return;
        }

        $query = User::query()
            ->where('tenant_id', $tenantId)
            ->where('barangay_id', $barangayId)
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->whereNull('deleted_at')
            ->whereHas('officialProfile', function (Builder $profileQuery): void {
                $this->chairpersonFederationSyncService->applyChairpersonPositionConstraint($profileQuery);
            })
            ->whereHas('officialProfile.terms', function ($termQuery) {
                $termQuery
                    ->where('status', OfficialTerm::STATUS_ACTIVE)
                    ->whereDate('term_end', '>=', now()->startOfDay());
            });

        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'position' => 'This barangay already has an SK Chairperson account.',
            ]);
        }
    }

    public function assertUniqueFederationPosition(
        int $tenantId,
        ?string $federationPosition,
        ?int $ignoreProfileId = null,
    ): void {
        $federationPosition = trim((string) $federationPosition);

        if ($federationPosition === '') {
            return;
        }

        if (! in_array($federationPosition, OfficialProfile::FEDERATION_POSITIONS, true)) {
            throw ValidationException::withMessages([
                'federation_position' => 'The selected federation position is not valid.',
            ]);
        }

        $rosterUserIds = $this->federationRosterQuery($tenantId)->pluck('id');

        $query = OfficialProfile::query()
            ->where('tenant_id', $tenantId)
            ->where('federation_position', $federationPosition)
            ->whereIn('user_id', $rosterUserIds);

        if ($ignoreProfileId !== null) {
            $query->where('id', '!=', $ignoreProfileId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'federation_position' => "The federation position \"{$federationPosition}\" is already assigned to another member.",
            ]);
        }
    }

    public function assignFederationPosition(User $account, ?string $federationPosition, User $admin): User
    {
        $profile = $account->officialProfile;
        $position = (string) ($profile?->position ?? '');

        if ($account->role !== User::ROLE_SK_OFFICIAL || ! $this->isChairPosition($position)) {
            throw ValidationException::withMessages([
                'account' => 'Only SK Chairperson accounts listed in the federation roster can be assigned a federation position.',
            ]);
        }

        if ($account->tenant_id !== $admin->tenant_id) {
            throw ValidationException::withMessages([
                'account' => 'Target account is outside your tenant scope.',
            ]);
        }

        if (! $profile) {
            throw ValidationException::withMessages([
                'account' => 'Official profile not found for this account.',
            ]);
        }

        $this->chairpersonFederationSyncService->syncForUser($account, $position);

        $normalizedPosition = trim((string) $federationPosition);

        $this->assertUniqueFederationPosition(
            (int) $account->tenant_id,
            $normalizedPosition !== '' ? $normalizedPosition : null,
            (int) $profile->id,
        );

        $profile->forceFill([
            'federation_position' => $normalizedPosition !== '' ? $normalizedPosition : null,
        ])->save();

        $this->chairpersonFederationSyncService->syncPortalAccessFromFederationPosition(
            $account->fresh(),
            $normalizedPosition !== '' ? $normalizedPosition : null,
        );

        if (
            $normalizedPosition !== ''
            && in_array($normalizedPosition, OfficialProfile::FEDERATION_PORTAL_ACCESS_POSITIONS, true)
        ) {
            try {
                $portalUrl = rtrim((string) config('app.url'), '/');
                $account->notify(new FederationPositionAssignedNotification($normalizedPosition, $portalUrl));
            } catch (\Throwable $exception) {
                Log::warning('Failed to send federation position assignment email.', [
                    'user_id' => $account->getKey(),
                    'position' => $normalizedPosition,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $account->fresh(['officialProfile.latestTerm', 'barangay']);
    }
}
