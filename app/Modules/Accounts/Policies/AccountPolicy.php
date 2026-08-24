<?php

namespace App\Modules\Accounts\Policies;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Authentication\Services\TenantContextService;
use App\Modules\Shared\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isFederationAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isFederationAdministrator();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isFederationAdministrator()
            && $this->userCanManageAccount($user, $target);
    }

    public function deactivate(User $user, User $target): bool
    {
        return $user->isFederationAdministrator()
            && $this->userCanManageAccount($user, $target);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->update($user, $target);
    }

    public function extendTerm(User $user, OfficialProfile $profile): bool
    {
        return $user->isFederationAdministrator()
            && $this->userCanManageProfile($user, $profile);
    }

    private function userCanManageAccount(User $user, User $target): bool
    {
        if ($target->isIncomingTurnoverOfficer()) {
            return false;
        }

        if ($user->isSkFed()) {
            return $user->tenant_id !== null
                && $target->tenant_id !== null
                && $user->tenant_id === $target->tenant_id;
        }

        $federationTenantId = app(TenantContextService::class)->tenantId();

        return $federationTenantId !== null
            && (int) $target->tenant_id === $federationTenantId;
    }

    private function userCanManageProfile(User $user, OfficialProfile $profile): bool
    {
        if ($user->isSkFed()) {
            return $user->tenant_id !== null
                && $profile->tenant_id !== null
                && $user->tenant_id === $profile->tenant_id;
        }

        $federationTenantId = app(TenantContextService::class)->tenantId();

        return $federationTenantId !== null
            && (int) $profile->tenant_id === $federationTenantId;
    }
}
