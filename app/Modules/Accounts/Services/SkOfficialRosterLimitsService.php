<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class SkOfficialRosterLimitsService
{
    public const MAX_OFFICIALS_PER_BARANGAY = 10;

    public const MAX_KAGAWAD_PER_BARANGAY = 7;

    public function __construct(
        private readonly ChairpersonFederationSyncService $chairpersonFederationSyncService,
    ) {
    }

    public function assertRosterLimits(
        int $tenantId,
        int $barangayId,
        string $position,
        ?int $ignoreUserId = null,
        ?string $proposedTermStart = null,
        ?string $proposedTermEnd = null,
    ): void {
        $normalized = $this->normalizePosition($position);

        $activeQuery = $this->activeOfficialsQuery(
            $tenantId,
            $barangayId,
            $ignoreUserId,
            $proposedTermStart,
            $proposedTermEnd,
        );
        $total = (clone $activeQuery)->count();

        if ($total >= self::MAX_OFFICIALS_PER_BARANGAY) {
            throw ValidationException::withMessages([
                'barangay_id' => 'This barangay already has the maximum of '.self::MAX_OFFICIALS_PER_BARANGAY.' SK Officials.',
            ]);
        }

        if ($this->chairpersonFederationSyncService->isChairpersonPosition($position)) {
            $chairCount = $this->countByPositionKind($activeQuery, 'chairperson');
            if ($chairCount >= 1) {
                throw ValidationException::withMessages([
                    'position' => 'This barangay already has an SK Chairperson.',
                ]);
            }

            return;
        }

        if ($normalized === 'secretary') {
            if ($this->countByPositionKind($activeQuery, 'secretary') >= 1) {
                throw ValidationException::withMessages([
                    'position' => 'This barangay already has an SK Secretary.',
                ]);
            }

            return;
        }

        if ($normalized === 'treasurer') {
            if ($this->countByPositionKind($activeQuery, 'treasurer') >= 1) {
                throw ValidationException::withMessages([
                    'position' => 'This barangay already has an SK Treasurer.',
                ]);
            }

            return;
        }

        if ($normalized === 'kagawad') {
            if ($this->countByPositionKind($activeQuery, 'kagawad') >= self::MAX_KAGAWAD_PER_BARANGAY) {
                throw ValidationException::withMessages([
                    'position' => 'This barangay already has the maximum of '.self::MAX_KAGAWAD_PER_BARANGAY.' SK Kagawad.',
                ]);
            }
        }
    }

    /**
     * @return Builder<User>
     */
    private function activeOfficialsQuery(
        int $tenantId,
        int $barangayId,
        ?int $ignoreUserId,
        ?string $proposedTermStart = null,
        ?string $proposedTermEnd = null,
    ): Builder {
        $query = User::query()
            ->where('tenant_id', $tenantId)
            ->where('barangay_id', $barangayId)
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->whereNull('deleted_at')
            ->whereHas('officialProfile.terms', function ($termQuery) use ($proposedTermStart, $proposedTermEnd) {
                $termQuery
                    ->where('status', OfficialTerm::STATUS_ACTIVE)
                    ->whereDate('term_end', '>=', now()->startOfDay());

                if ($proposedTermStart !== null && $proposedTermEnd !== null
                    && $proposedTermStart !== '' && $proposedTermEnd !== '') {
                    $termQuery
                        ->whereDate('term_start', '<=', $proposedTermEnd)
                        ->whereDate('term_end', '>=', $proposedTermStart);
                }
            });

        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query;
    }

    /**
     * @param  Builder<User>  $activeQuery
     */
    private function countByPositionKind(Builder $activeQuery, string $kind): int
    {
        return (clone $activeQuery)
            ->whereHas('officialProfile', function (Builder $profileQuery) use ($kind) {
                if ($kind === 'chairperson') {
                    $this->chairpersonFederationSyncService->applyChairpersonPositionConstraint($profileQuery);

                    return;
                }

                $profileQuery->whereRaw('LOWER(TRIM(position)) = ?', [$kind]);
            })
            ->count();
    }

    private function normalizePosition(string $position): string
    {
        $value = strtolower(trim($position));

        if (str_contains($value, 'kagawad')) {
            return 'kagawad';
        }

        if (str_contains($value, 'secretary')) {
            return 'secretary';
        }

        if (str_contains($value, 'treasurer')) {
            return 'treasurer';
        }

        if (str_contains($value, 'chair')) {
            return 'chairperson';
        }

        return $value;
    }
}
