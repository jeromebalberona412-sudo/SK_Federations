<?php

namespace App\Modules\Turnover\Services;

use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Models\User;
use App\Modules\Turnover\Models\FederationTurnover;
use App\Services\SkFederationsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TurnoverService
{
    public function __construct(
        private readonly FederationTermDetectionService $termDetectionService,
        private readonly AuditLogInterface $auditLog,
        private readonly SkFederationsNotificationService $notificationService,
    ) {
    }

    public function dashboardContext(User $user): array
    {
        $tenantId = (int) $user->tenant_id;
        $leadership = $this->termDetectionService->currentLeadershipContext($tenantId);
        $activeTurnover = $this->termDetectionService->activeTurnoverForTenant($tenantId);

        return [
            'show_start_notice' => $this->termDetectionService->canStartNewTurnover($user),
            'show_modal' => $this->termDetectionService->shouldShowTurnoverModal($user),
            'portal_locked' => $this->termDetectionService->mustLockPortalForTurnover($user),
            'term_ended' => $this->termDetectionService->isTermEnded($leadership['term_end'] ?? null),
            'show_complete_card' => $this->termDetectionService->shouldShowCompleteTurnoverCard($user, $activeTurnover),
            'active_turnover' => $activeTurnover,
            'leadership' => $leadership,
            'progress' => $activeTurnover ? $this->progressStages($activeTurnover) : [],
        ];
    }

    public function startTurnover(User $user, Request $request): FederationTurnover
    {
        if (! $this->termDetectionService->isFederationLeadershipOfficer($user)) {
            throw ValidationException::withMessages([
                'turnover' => 'Only the Federation President or Vice President can start turnover.',
            ]);
        }

        $tenantId = (int) $user->tenant_id;
        $context = $this->termDetectionService->currentLeadershipContext($tenantId);

        if ($context['term_end'] === null || ! $this->termDetectionService->canStartNewTurnover($user)) {
            throw ValidationException::withMessages([
                'turnover' => 'Turnover is not available for the current term window.',
            ]);
        }

        if ($this->termDetectionService->activeTurnoverForTenant($tenantId) !== null) {
            throw ValidationException::withMessages([
                'turnover' => 'A turnover process is already in progress.',
            ]);
        }

        return DB::transaction(function () use ($user, $request, $tenantId, $context): FederationTurnover {
            $turnover = FederationTurnover::query()->create([
                'tenant_id' => $tenantId,
                'current_term_id' => $context['current_term_id'],
                'previous_president_id' => $context['president']?->id,
                'previous_vice_president_id' => $context['vice_president']?->id,
                'started_by' => $user->id,
                'status' => FederationTurnover::STATUS_PENDING_REGISTRATION,
                'started_at' => now(),
                'started_ip' => $request->ip(),
                'started_user_agent' => (string) $request->userAgent(),
            ]);

            $this->auditLog->log('turnover_started', $user, [
                'turnover_id' => $turnover->id,
                'current_term_id' => $turnover->current_term_id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $this->notificationService->notifyUser(
                $user,
                SkFederationsNotificationService::CATEGORY_GENERAL,
                'Federation Turnover Started',
                'You have started the federation turnover process. Register the incoming officers to continue.',
                route('dashboard'),
            );

            return $turnover;
        });
    }

    public function remindLater(User $user): void
    {
        $hours = max(1, (int) config('turnover.remind_later_hours', 24));

        $user->forceFill([
            'turnover_notice_dismissed_until' => now()->addHours($hours),
        ])->save();
    }

    /**
     * @return list<array{key: string, label: string, status: string}>
     */
    public function progressStages(FederationTurnover $turnover): array
    {
        $status = $turnover->status;

        $stageOrder = [
            FederationTurnover::STATUS_PENDING_REGISTRATION => 0,
            FederationTurnover::STATUS_PENDING_ACCOUNT_SETUP => 1,
            FederationTurnover::STATUS_PENDING_CONFIRMATION => 2,
            FederationTurnover::STATUS_COMPLETED => 3,
        ];

        $current = $stageOrder[$status] ?? 0;

        $stages = [
            ['key' => 'registration', 'label' => 'Register Incoming Officers'],
            ['key' => 'account_setup', 'label' => 'Account Setup'],
            ['key' => 'confirmation', 'label' => 'Complete Turnover'],
            ['key' => 'completed', 'label' => 'Turnover Completed'],
        ];

        return collect($stages)->map(function (array $stage, int $index) use ($current, $status): array {
            if ($status === FederationTurnover::STATUS_COMPLETED) {
                $stageStatus = 'completed';
            } elseif ($index < $current) {
                $stageStatus = 'completed';
            } elseif ($index === $current) {
                $stageStatus = 'current';
            } else {
                $stageStatus = 'pending';
            }

            $stage['status'] = $stageStatus;

            return $stage;
        })->all();
    }
}
