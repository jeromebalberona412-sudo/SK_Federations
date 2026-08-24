<?php

namespace App\Modules\Turnover\Policies;

use App\Modules\Shared\Models\User;
use App\Modules\Turnover\Models\FederationTurnover;
use App\Modules\Turnover\Services\FederationTermDetectionService;

class FederationTurnoverPolicy
{
    public function __construct(
        private readonly FederationTermDetectionService $termDetectionService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->termDetectionService->isFederationLeadershipOfficer($user);
    }

    public function view(User $user, FederationTurnover $turnover): bool
    {
        return (int) $user->tenant_id === (int) $turnover->tenant_id
            && $this->termDetectionService->isFederationLeadershipOfficer($user);
    }

    public function start(User $user): bool
    {
        return $this->termDetectionService->isFederationLeadershipOfficer($user);
    }

    public function register(User $user, FederationTurnover $turnover): bool
    {
        return $this->view($user, $turnover)
            && $turnover->status === FederationTurnover::STATUS_PENDING_REGISTRATION;
    }

    public function complete(User $user, FederationTurnover $turnover): bool
    {
        return $this->view($user, $turnover)
            && $turnover->status === FederationTurnover::STATUS_PENDING_CONFIRMATION;
    }
}
