<?php

namespace App\Modules\Rejected_Abyip\Services;

use App\Modules\Barangay_ABYIP\Services\BarangayAbyipService;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RejectedAbyipService
{
    public const RETENTION_DAYS = 30;

    public function __construct(
        private readonly BarangayAbyipService $abyipService,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listRejected(): Collection
    {
        return $this->abyipService->listRejected(self::RETENTION_DAYS);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        return $this->abyipService->showRejected($id, self::RETENTION_DAYS);
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(User $actor, int $id): array
    {
        return $this->abyipService->restoreRejected($actor, $id);
    }

    public function permanentlyDelete(User $actor, int $id): void
    {
        $this->abyipService->permanentlyDeleteRejected($actor, $id);
    }

    public function purgeExpired(int $days = self::RETENTION_DAYS): int
    {
        return $this->abyipService->purgeExpiredRejected($days);
    }

    public function daysRemaining(?Carbon $rejectedAt): int
    {
        if ($rejectedAt === null) {
            return 0;
        }

        $expiresAt = $rejectedAt->copy()->addDays(self::RETENTION_DAYS);

        return max(0, (int) now()->diffInDays($expiresAt, false));
    }

    public function daysRemainingTier(int $daysRemaining): string
    {
        if ($daysRemaining <= 3) {
            return 'red';
        }

        if ($daysRemaining <= 7) {
            return 'orange';
        }

        return 'green';
    }
}
