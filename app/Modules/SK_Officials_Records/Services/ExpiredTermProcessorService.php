<?php

namespace App\Modules\SK_Officials_Records\Services;

use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;

class ExpiredTermProcessorService
{
    public function __construct(
        private readonly SkOfficialsRecordsService $skOfficialsRecordsService,
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
                if ($user->role === User::ROLE_SK_FED) {
                    if (class_exists(\App\Modules\SK_Federation_Records\Services\SkFederationRecordsService::class)) {
                        app(\App\Modules\SK_Federation_Records\Services\SkFederationRecordsService::class)
                            ->archiveCompletedTerm($term->fresh(), $archivedBy);
                    }
                } else {
                    $this->skOfficialsRecordsService->archiveCompletedTerm($term->fresh(), $archivedBy);
                }

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
