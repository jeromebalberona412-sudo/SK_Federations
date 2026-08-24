<?php

namespace App\Modules\Turnover\Services;

use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Services\ChairpersonFederationSyncService;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Models\User;
use App\Modules\Turnover\Models\FederationTurnover;
use App\Modules\Turnover\Models\FederationTurnoverRegistration;
use App\Modules\Turnover\Notifications\TurnoverCompletedNotification;
use App\Services\SkFederationsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TurnoverCompletionService
{
    public function __construct(
        private readonly FederationTermDetectionService $termDetectionService,
        private readonly ChairpersonFederationSyncService $chairpersonFederationSyncService,
        private readonly AuditLogInterface $auditLog,
        private readonly SkFederationsNotificationService $notificationService,
    ) {
    }

    public function completeTurnover(FederationTurnover $turnover, User $admin, Request $request): FederationTurnover
    {
        if ($turnover->status !== FederationTurnover::STATUS_PENDING_CONFIRMATION) {
            throw ValidationException::withMessages([
                'turnover' => 'Turnover is not ready to be completed.',
            ]);
        }

        if (! $this->termDetectionService->bothIncomingOfficersReady($turnover)) {
            throw ValidationException::withMessages([
                'turnover' => 'Both incoming officers must complete account setup first.',
            ]);
        }

        if (! $this->termDetectionService->isFederationLeadershipOfficer($admin)) {
            throw ValidationException::withMessages([
                'turnover' => 'Only the Federation President or Vice President can complete turnover.',
            ]);
        }

        return DB::transaction(function () use ($turnover, $admin, $request): FederationTurnover {
            $this->deactivateOutgoingOfficer($turnover->previousPresident);
            $this->deactivateOutgoingOfficer($turnover->previousVicePresident);

            $this->activateIncomingOfficer($turnover, 'President', (int) $turnover->new_president_id);
            $this->activateIncomingOfficer($turnover, 'Vice President', (int) $turnover->new_vice_president_id);

            $turnover->forceFill([
                'status' => FederationTurnover::STATUS_COMPLETED,
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
                'confirmed_ip' => $request->ip(),
                'confirmed_user_agent' => (string) $request->userAgent(),
            ])->save();

            $this->auditLog->log('turnover_completed', $admin, [
                'turnover_id' => $turnover->id,
                'previous_term_id' => $turnover->current_term_id,
                'new_term_id' => $turnover->new_term_id,
                'previous_president_id' => $turnover->previous_president_id,
                'previous_vice_president_id' => $turnover->previous_vice_president_id,
                'new_president_id' => $turnover->new_president_id,
                'new_vice_president_id' => $turnover->new_vice_president_id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $this->notifyTurnoverCompleted($turnover);

            return $turnover->fresh(['registrations']);
        });
    }

    private function deactivateOutgoingOfficer(?User $user): void
    {
        if ($user === null) {
            return;
        }

        $user->loadMissing('officialProfile.terms');

        $user->forceFill([
            'status' => User::STATUS_INACTIVE,
            'account_status' => 'archived',
            'turnover_status' => 'archived',
            'has_federation_access' => false,
            'active_session_id' => null,
        ])->save();

        $profile = $user->officialProfile;

        if ($profile) {
            $profile->forceFill(['federation_position' => null])->save();

            $profile->terms()
                ->where('status', OfficialTerm::STATUS_ACTIVE)
                ->update(['status' => OfficialTerm::STATUS_REPLACED]);
        }

        $this->chairpersonFederationSyncService->syncPortalAccessFromFederationPosition($user->fresh(), null);

        $this->invalidateUserSessions($user);
    }

    private function activateIncomingOfficer(FederationTurnover $turnover, string $position, int $userId): void
    {
        $user = User::query()->with(['officialProfile.terms'])->find($userId);

        if ($user === null) {
            return;
        }

        $profile = $user->officialProfile;

        if ($profile) {
            $profile->forceFill(['federation_position' => $position])->save();

            $inactiveTerm = $profile->terms()
                ->where('status', OfficialTerm::STATUS_INACTIVE)
                ->latest('term_end')
                ->first();

            if ($inactiveTerm) {
                $profile->terms()
                    ->where('status', OfficialTerm::STATUS_ACTIVE)
                    ->update(['status' => OfficialTerm::STATUS_REPLACED]);

                $inactiveTerm->forceFill(['status' => OfficialTerm::STATUS_ACTIVE])->save();

                if ($position === 'President') {
                    $turnover->forceFill(['new_term_id' => $inactiveTerm->id])->save();
                }
            }
        }

        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'account_status' => 'active',
            'turnover_status' => 'activated',
            'activated_term_id' => $profile?->terms()->where('status', OfficialTerm::STATUS_ACTIVE)->value('id'),
            'has_federation_access' => true,
        ])->save();

        $this->chairpersonFederationSyncService->syncForUser($user->fresh(), 'Chairperson');
        $this->chairpersonFederationSyncService->syncPortalAccessFromFederationPosition($user->fresh(), $position);

        FederationTurnoverRegistration::query()
            ->where('federation_turnover_id', $turnover->id)
            ->where('user_id', $user->id)
            ->update([
                'status' => FederationTurnoverRegistration::STATUS_ACTIVATED,
                'activated_at' => now(),
            ]);

        try {
            $user->notify(new TurnoverCompletedNotification(rtrim((string) config('app.url'), '/')));
        } catch (\Throwable $exception) {
            Log::warning('Failed to send turnover completion email.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->notificationService->notifyUser(
            $user,
            SkFederationsNotificationService::CATEGORY_GENERAL,
            'Federation Turnover Completed',
            'Your administrative account is now active. You may now login to SK One Portal.',
            route('dashboard'),
        );
    }

    private function invalidateUserSessions(User $user): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function notifyTurnoverCompleted(FederationTurnover $turnover): void
    {
        foreach ([$turnover->previousPresident, $turnover->previousVicePresident] as $outgoing) {
            if (! $outgoing instanceof User) {
                continue;
            }

            $this->notificationService->notifyUser(
                $outgoing,
                SkFederationsNotificationService::CATEGORY_GENERAL,
                'Federation Turnover Completed',
                'Your term has ended. Your account has been archived. Thank you for your service.',
                route('login'),
            );
        }
    }
}
