<?php

namespace App\Modules\Turnover\Services;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Services\FederationRosterService;
use App\Modules\Shared\Models\User;
use App\Modules\Turnover\Models\FederationTurnover;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FederationTermDetectionService
{
    public function __construct(
        private readonly FederationRosterService $federationRosterService,
    ) {
    }

    public function isFederationLeadershipOfficer(User $user): bool
    {
        if (! $user->hasFederationLeadershipAccess()) {
            return false;
        }

        $position = trim((string) ($user->officialProfile?->federation_position ?? ''));

        return in_array($position, ['President', 'Vice President'], true);
    }

    /**
     * @return array{president: User|null, vice_president: User|null, term_end: Carbon|null, current_term_id: int|null}
     */
    public function currentLeadershipContext(int $tenantId): array
    {
        $roster = $this->federationRosterService
            ->federationRosterQuery($tenantId)
            ->with(['officialProfile.latestTerm'])
            ->get();

        $president = $this->findOfficerByPosition($roster, 'President');
        $vicePresident = $this->findOfficerByPosition($roster, 'Vice President');

        $termEnd = $this->resolveLeadershipTermEnd($president, $vicePresident);
        $currentTermId = $this->resolveCurrentTermId($president, $vicePresident);

        return [
            'president' => $president,
            'vice_president' => $vicePresident,
            'term_end' => $termEnd,
            'current_term_id' => $currentTermId,
        ];
    }

    public function isTermEnded(?Carbon $termEnd): bool
    {
        if ($termEnd === null) {
            return false;
        }

        return now()->startOfDay()->greaterThan($termEnd->copy()->startOfDay());
    }

    public function shouldShowTurnoverModal(User $user): bool
    {
        if (! $this->isFederationLeadershipOfficer($user)) {
            return false;
        }

        $tenantId = (int) $user->tenant_id;
        $context = $this->currentLeadershipContext($tenantId);

        if ($context['term_end'] === null) {
            return false;
        }

        if ($this->activeTurnoverForTenant($tenantId) !== null) {
            return true;
        }

        if ($this->isTermEnded($context['term_end'])) {
            return ! $this->hasCompletedTurnoverForTerm($tenantId, $context['current_term_id']);
        }

        return $this->isWithinNoticeWindow($context['term_end']);
    }

    public function mustLockPortalForTurnover(User $user): bool
    {
        if (! $this->isFederationLeadershipOfficer($user)) {
            return false;
        }

        $tenantId = (int) $user->tenant_id;
        $context = $this->currentLeadershipContext($tenantId);

        if ($context['term_end'] === null || ! $this->isTermEnded($context['term_end'])) {
            return false;
        }

        return ! $this->hasCompletedTurnoverForTerm($tenantId, $context['current_term_id']);
    }

    public function canStartNewTurnover(User $user): bool
    {
        if (! $this->isFederationLeadershipOfficer($user)) {
            return false;
        }

        $tenantId = (int) $user->tenant_id;
        $context = $this->currentLeadershipContext($tenantId);

        if ($context['term_end'] === null) {
            return false;
        }

        if ($this->activeTurnoverForTenant($tenantId) !== null) {
            return false;
        }

        return $this->isWithinNoticeWindow($context['term_end'])
            || $this->isTermEnded($context['term_end']);
    }

    public function shouldShowTurnoverNotice(User $user): bool
    {
        return $this->canStartNewTurnover($user);
    }

    public function shouldShowCompleteTurnoverCard(User $user, ?FederationTurnover $turnover = null): bool
    {
        if (! $this->isFederationLeadershipOfficer($user)) {
            return false;
        }

        $turnover ??= $this->activeTurnoverForTenant((int) $user->tenant_id);

        if ($turnover === null || $turnover->status !== FederationTurnover::STATUS_PENDING_CONFIRMATION) {
            return false;
        }

        return $this->bothIncomingOfficersReady($turnover);
    }

    public function activeTurnoverForTenant(int $tenantId): ?FederationTurnover
    {
        return FederationTurnover::query()
            ->with(['registrations'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', FederationTurnover::ACTIVE_STATUSES)
            ->latest('id')
            ->first();
    }

    public function bothIncomingOfficersReady(FederationTurnover $turnover): bool
    {
        $registrations = $turnover->registrations;

        if ($registrations->count() < 2) {
            return false;
        }

        return $registrations->every(
            fn ($registration) => $registration->hasCompletedAccountSetup()
        );
    }

    private function hasCompletedTurnoverForTerm(int $tenantId, ?int $currentTermId): bool
    {
        $query = FederationTurnover::query()
            ->where('tenant_id', $tenantId)
            ->where('status', FederationTurnover::STATUS_COMPLETED);

        if ($currentTermId !== null) {
            $query->where('current_term_id', $currentTermId);
        }

        return $query->exists();
    }

    private function hasActiveTurnover(int $tenantId, ?int $currentTermId): bool
    {
        $query = FederationTurnover::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', FederationTurnover::ACTIVE_STATUSES);

        if ($currentTermId !== null) {
            $query->where('current_term_id', $currentTermId);
        }

        return $query->exists();
    }

    private function isWithinNoticeWindow(Carbon $termEnd): bool
    {
        $today = now()->startOfDay();
        $noticeDays = max(1, (int) config('turnover.notice_days_before_term_end', 7));
        $noticeStart = $termEnd->copy()->subDays($noticeDays)->startOfDay();

        return $today->greaterThanOrEqualTo($noticeStart) && ! $today->greaterThan($termEnd->copy()->startOfDay());
    }

    /**
     * @param  Collection<int, User>  $roster
     */
    private function findOfficerByPosition(Collection $roster, string $position): ?User
    {
        return $roster->first(function (User $user) use ($position): bool {
            return trim((string) ($user->officialProfile?->federation_position ?? '')) === $position;
        });
    }

    private function resolveLeadershipTermEnd(?User $president, ?User $vicePresident): ?Carbon
    {
        $dates = collect([$president, $vicePresident])
            ->filter()
            ->map(fn (User $user) => $user->officialProfile?->latestTerm?->term_end)
            ->filter()
            ->map(fn ($date) => Carbon::parse($date));

        return $dates->max();
    }

    private function resolveCurrentTermId(?User $president, ?User $vicePresident): ?int
    {
        $presidentTerm = $president?->officialProfile?->latestTerm;

        if ($presidentTerm?->status === OfficialTerm::STATUS_ACTIVE) {
            return (int) $presidentTerm->id;
        }

        $viceTerm = $vicePresident?->officialProfile?->latestTerm;

        if ($viceTerm?->status === OfficialTerm::STATUS_ACTIVE) {
            return (int) $viceTerm->id;
        }

        return $presidentTerm?->id ?? $viceTerm?->id;
    }
}
