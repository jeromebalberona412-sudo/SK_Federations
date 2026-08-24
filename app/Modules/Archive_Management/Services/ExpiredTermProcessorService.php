<?php

namespace App\Modules\Archive_Management\Services;

use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;

class ExpiredTermProcessorService
{
    public function __construct(
        private readonly TermRecordsArchiveService $termRecordsArchiveService,
    ) {
    }

    public function processForTenant(?int $tenantId, ?User $archivedBy = null): int
    {
        $today = now()->startOfDay();

        $terms = OfficialTerm::query()
            ->where('status', OfficialTerm::STATUS_ACTIVE)
            ->whereDate('term_end', '<=', $today)
            ->whereHas('officialProfile.user', function ($query) use ($tenantId) {
                $query->whereIn('role', [User::ROLE_SK_OFFICIAL, User::ROLE_SK_FED]);

                if ($tenantId !== null) {
                    $query->where('tenant_id', $tenantId);
                }
            })
            ->with(['officialProfile.user'])
            ->get();

        $processed = 0;

        foreach ($terms as $term) {
            $user = $term->officialProfile?->user;

            if ($user === null) {
                continue;
            }

            DB::transaction(function () use ($term, $user, $archivedBy, &$processed) {
                $this->termRecordsArchiveService->archiveCompletedTerm($term->fresh(), $archivedBy);

                $term->update(['status' => OfficialTerm::STATUS_EXPIRED]);

                if (! $user->trashed()) {
                    $user->forceFill(['status' => User::STATUS_INACTIVE])->save();
                }

                $processed++;
            });
        }

        return $processed;
    }
}
