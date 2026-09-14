<?php

namespace App\Modules\SK_Officials_Records\Services;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Shared\Models\User;
use App\Modules\SK_Federation_Records\Services\SkFederationRecordsService;
use App\Modules\Turnover\Services\FederationTermDetectionService;
use Illuminate\Support\Facades\DB;

class ExpiredTermProcessorService
{
    public function __construct(
        private readonly SkOfficialsRecordsService $skOfficialsRecordsService,
    ) {}

    public function processForTenant(?int $tenantId, ?User $archivedBy = null): int
    {
        $today = now()->startOfDay();

        // Only SK Officials (barangay chairpersons / officials) are term-bound.
        // SK Federation Administrators (role sk_fed) have no term lifecycle.
        $terms = OfficialTerm::query()
            ->whereDate('term_end', '<', $today)
            ->whereIn('status', [
                OfficialTerm::STATUS_ACTIVE,
                OfficialTerm::STATUS_EXPIRED,
                OfficialTerm::STATUS_REPLACED,
            ])
            ->with(['officialProfile'])
            ->whereHas('officialProfile.user', function ($query) use ($tenantId) {
                // Include soft-deleted accounts so historical snapshots are still created.
                $query->withTrashed()->where('role', User::ROLE_SK_OFFICIAL);

                if ($tenantId !== null) {
                    $query->where('tenant_id', $tenantId);
                }
            })
            ->get();

        $processed = 0;
        $termDetection = app(FederationTermDetectionService::class);

        foreach ($terms as $term) {
            $profile = $term->officialProfile;
            $userId = $profile?->user_id;
            $user = $userId !== null
                ? User::withTrashed()->with('barangay')->find($userId)
                : null;

            if ($user === null || $user->isSkFed()) {
                continue;
            }

            DB::transaction(function () use ($term, $user, $archivedBy, $termDetection, &$processed) {
                $freshTerm = $term->fresh(['officialProfile']) ?? $term;

                // Always create/update the historical archive snapshot for ended terms.
                $this->archiveEndedTerm($freshTerm, $user, $archivedBy);

                // Keep outgoing President/VP loginable until federation turnover is done.
                if ($this->shouldDeferLeadershipArchive($user, $freshTerm, $termDetection)) {
                    $processed++;

                    return;
                }

                if ($freshTerm->status !== OfficialTerm::STATUS_EXPIRED
                    && $freshTerm->status !== OfficialTerm::STATUS_REPLACED) {
                    $freshTerm->update(['status' => OfficialTerm::STATUS_EXPIRED]);
                }

                if (! $user->trashed() && $user->status !== User::STATUS_INACTIVE) {
                    $user->forceFill([
                        'status' => User::STATUS_INACTIVE,
                        'has_federation_access' => false,
                        'active_session_id' => null,
                    ])->save();
                }

                $profile = $user->officialProfile;
                if ($profile && filled($profile->federation_position)) {
                    $profile->forceFill(['federation_position' => null])->save();
                }

                $processed++;
            });
        }

        return $processed;
    }

    private function archiveEndedTerm(OfficialTerm $term, User $user, ?User $archivedBy): void
    {
        $profile = $term->officialProfile;

        // Federation Records: only Chairpersons with an assigned federation position
        // (President, Vice President, Secretary, Treasurer, PIO, Sergeant at Arms).
        if ($profile !== null
            && $this->shouldArchiveToFederationRecords($profile)
            && class_exists(SkFederationRecordsService::class)
        ) {
            app(SkFederationRecordsService::class)->archiveCompletedTerm($term, $archivedBy);
        }

        // Officials Records: every ended SK Official term.
        $this->skOfficialsRecordsService->archiveCompletedTerm($term, $archivedBy);
    }

    private function shouldArchiveToFederationRecords(OfficialProfile $profile): bool
    {
        $federationPositions = OfficialProfile::FEDERATION_POSITIONS;
        $fedPosition = trim((string) ($profile->federation_position ?? ''));

        if ($fedPosition !== '' && in_array($fedPosition, $federationPositions, true)) {
            return true;
        }

        $position = trim((string) ($profile->position ?? ''));

        return $position !== '' && in_array($position, $federationPositions, true);
    }

    private function shouldDeferLeadershipArchive(
        User $user,
        OfficialTerm $term,
        FederationTermDetectionService $termDetection,
    ): bool {
        if (! (bool) $user->has_federation_access) {
            return false;
        }

        $position = trim((string) ($term->officialProfile?->federation_position
            ?? $user->officialProfile?->federation_position
            ?? ''));

        if (! in_array($position, OfficialProfile::FEDERATION_PORTAL_ACCESS_POSITIONS, true)) {
            return false;
        }

        // Keep outgoing President/VP active until federation turnover is completed.
        return ! $termDetection->hasCompletedTurnoverForTerm(
            (int) $user->tenant_id,
            (int) $term->id,
        );
    }
}
