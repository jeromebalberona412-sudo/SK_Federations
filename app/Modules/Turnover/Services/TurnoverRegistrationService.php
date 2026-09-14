<?php

namespace App\Modules\Turnover\Services;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Services\AccountService;
use App\Modules\Accounts\Services\SkOfficialRosterLimitsService;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Models\User;
use App\Modules\Turnover\Models\FederationTurnover;
use App\Modules\Turnover\Models\FederationTurnoverRegistration;
use App\Modules\Turnover\Notifications\TurnoverAccountSetupNotification;
use App\Services\SkFederationsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TurnoverRegistrationService
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly SkOfficialRosterLimitsService $rosterLimitsService,
        private readonly TurnoverInvitationService $invitationService,
        private readonly FederationTermDetectionService $termDetectionService,
        private readonly AuditLogInterface $auditLog,
        private readonly SkFederationsNotificationService $notificationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $presidentData
     * @param  array<string, mixed>  $vicePresidentData
     */
    public function registerIncomingOfficers(
        FederationTurnover $turnover,
        array $presidentData,
        array $vicePresidentData,
        User $admin,
        Request $request,
    ): FederationTurnover {
        if (! $turnover->isEditable() || $turnover->status !== FederationTurnover::STATUS_PENDING_REGISTRATION) {
            throw ValidationException::withMessages([
                'turnover' => 'This turnover can no longer accept registrations.',
            ]);
        }

        $this->assertNoDuplicateEmails($presidentData, $vicePresidentData);

        return DB::transaction(function () use ($turnover, $presidentData, $vicePresidentData, $admin, $request): FederationTurnover {
            $president = $this->createIncomingOfficer($turnover, $presidentData, 'President', $admin);
            $vicePresident = $this->createIncomingOfficer($turnover, $vicePresidentData, 'Vice President', $admin);

            $turnover->forceFill([
                'new_president_id' => $president['user']->id,
                'new_vice_president_id' => $vicePresident['user']->id,
                'new_term_id' => $president['term']->id,
                'status' => FederationTurnover::STATUS_PENDING_ACCOUNT_SETUP,
            ])->save();

            foreach ([$president, $vicePresident] as $officer) {
                if (($officer['skip_setup_invite'] ?? false) === true) {
                    $this->markAccountSetupCompleted($officer['user']);
                } else {
                    $this->invitationService->sendSetupInvitation($officer['user'], $officer['registration']);
                }
            }

            $this->auditLog->log('turnover_officers_registered', $admin, [
                'turnover_id' => $turnover->id,
                'new_president_id' => $president['user']->id,
                'new_vice_president_id' => $vicePresident['user']->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            foreach ([$admin, $turnover->previousPresident, $turnover->previousVicePresident] as $recipient) {
                if ($recipient instanceof User) {
                    $this->notificationService->notifyUser(
                        $recipient,
                        SkFederationsNotificationService::CATEGORY_GENERAL,
                        'Incoming Officers Registered',
                        'Incoming Federation President and Vice President have been registered. Account setup emails have been sent where needed.',
                        route('dashboard'),
                    );
                }
            }

            return $turnover->fresh(['registrations']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, term: OfficialTerm, registration: FederationTurnoverRegistration}
     */
    private function createIncomingOfficer(
        FederationTurnover $turnover,
        array $data,
        string $federationPosition,
        User $admin,
    ): array {
        $normalized = $this->normalizeOfficerData($data, $federationPosition);

        $existing = User::query()
            ->where('tenant_id', (int) $admin->tenant_id)
            ->whereRaw('LOWER(email) = ?', [strtolower($normalized['email'])])
            ->whereNull('deleted_at')
            ->with(['officialProfile.terms', 'barangay'])
            ->first();

        if ($existing !== null) {
            return $this->attachExistingOfficialToTurnover(
                $turnover,
                $existing,
                $normalized,
                $federationPosition,
                $admin,
            );
        }

        $this->rosterLimitsService->assertRosterLimits(
            (int) $admin->tenant_id,
            (int) $normalized['barangay_id'],
            (string) $normalized['position'],
            null,
            (string) ($normalized['term_start'] ?? ''),
            (string) ($normalized['term_end'] ?? ''),
        );

        $user = $this->accountService->createUser(array_merge($normalized, [
            'status' => User::STATUS_INACTIVE,
        ]), $admin);

        $this->accountService->assignRole($user, User::ROLE_SK_OFFICIAL);
        $this->accountService->assignBarangay($user, (int) $normalized['barangay_id']);

        $profile = $this->accountService->createOfficialProfile($user, $normalized);
        $profile->forceFill([
            'federation_position' => $federationPosition,
        ])->save();

        $term = $this->accountService->createTermRecord($profile, [
            'term_start' => $normalized['term_start'],
            'term_end' => $normalized['term_end'],
            'status' => OfficialTerm::STATUS_INACTIVE,
        ]);

        $user->forceFill([
            'turnover_status' => 'awaiting_setup',
            'account_status' => 'turnover_pending',
            'has_federation_access' => false,
            'status' => User::STATUS_INACTIVE,
        ])->save();

        $registration = FederationTurnoverRegistration::query()->create([
            'federation_turnover_id' => $turnover->id,
            'user_id' => $user->id,
            'official_term_id' => $term->id,
            'position' => $federationPosition,
            'complete_name' => FederationTurnoverRegistration::formatFullName(
                $normalized['first_name'] ?? null,
                $normalized['middle_name'] ?? null,
                $normalized['last_name'] ?? null,
                $normalized['suffix'] ?? null,
            ),
            'email' => $user->email,
            'contact_number' => $normalized['contact_number'] ?? null,
            'municipality' => 'Santa Cruz',
            'term_start' => $normalized['term_start'],
            'term_end' => $normalized['term_end'],
            'status' => FederationTurnoverRegistration::STATUS_PENDING,
            'registration_payload' => $normalized,
        ]);

        return [
            'user' => $user->fresh(['officialProfile.latestTerm', 'barangay']),
            'term' => $term,
            'registration' => $registration,
            'skip_setup_invite' => false,
        ];
    }

    /**
     * Reuse an existing SK Official login account for federation turnover.
     * Never creates a second Auth/password identity for the same person.
     *
     * @param  array<string, mixed>  $normalized
     * @return array{user: User, term: OfficialTerm, registration: FederationTurnoverRegistration, skip_setup_invite: bool}
     */
    private function attachExistingOfficialToTurnover(
        FederationTurnover $turnover,
        User $existing,
        array $normalized,
        string $federationPosition,
        User $admin,
    ): array {
        if ($existing->role !== User::ROLE_SK_OFFICIAL) {
            throw ValidationException::withMessages([
                'email' => "Email {$normalized['email']} already belongs to a non-SK Official account and cannot be reused for turnover.",
            ]);
        }

        if ((int) $existing->tenant_id !== (int) $admin->tenant_id) {
            throw ValidationException::withMessages([
                'email' => "Email {$normalized['email']} is already registered outside your tenant scope.",
            ]);
        }

        $this->rosterLimitsService->assertRosterLimits(
            (int) $admin->tenant_id,
            (int) $normalized['barangay_id'],
            (string) $normalized['position'],
            (int) $existing->id,
            (string) ($normalized['term_start'] ?? ''),
            (string) ($normalized['term_end'] ?? ''),
        );

        $this->accountService->assignBarangay($existing, (int) $normalized['barangay_id']);

        $profile = $existing->officialProfile;
        if ($profile === null) {
            $profile = $this->accountService->createOfficialProfile($existing, $normalized);
        } else {
            $profile->forceFill([
                'first_name' => $normalized['first_name'] ?? $profile->first_name,
                'middle_name' => $normalized['middle_name'] ?? $profile->middle_name,
                'last_name' => $normalized['last_name'] ?? $profile->last_name,
                'suffix' => $normalized['suffix'] ?? $profile->suffix,
                'sex' => $normalized['sex'] ?? $profile->sex,
                'date_of_birth' => $normalized['date_of_birth'] ?? $profile->date_of_birth,
                'contact_number' => $normalized['contact_number'] ?? $profile->contact_number,
                'position' => 'Chairperson',
                'municipality' => $normalized['municipality'] ?? $profile->municipality,
                'province' => $normalized['province'] ?? $profile->province,
                'region' => $normalized['region'] ?? $profile->region,
                'federation_position' => $federationPosition,
            ])->save();
        }

        $term = $this->accountService->createTermRecord($profile, [
            'term_start' => $normalized['term_start'],
            'term_end' => $normalized['term_end'],
            'status' => OfficialTerm::STATUS_INACTIVE,
        ]);

        // Keep existing password/email identity and Officials portal access.
        // Federation portal unlocks only after turnover completion.
        $existing->forceFill([
            'turnover_status' => 'pending_confirmation',
            'account_status' => 'turnover_waiting',
            'has_federation_access' => false,
            'status' => User::STATUS_ACTIVE,
        ])->save();

        $registration = FederationTurnoverRegistration::query()->create([
            'federation_turnover_id' => $turnover->id,
            'user_id' => $existing->id,
            'official_term_id' => $term->id,
            'position' => $federationPosition,
            'complete_name' => FederationTurnoverRegistration::formatFullName(
                $normalized['first_name'] ?? null,
                $normalized['middle_name'] ?? null,
                $normalized['last_name'] ?? null,
                $normalized['suffix'] ?? null,
            ),
            'email' => $existing->email,
            'contact_number' => $normalized['contact_number'] ?? null,
            'municipality' => 'Santa Cruz',
            'term_start' => $normalized['term_start'],
            'term_end' => $normalized['term_end'],
            'status' => FederationTurnoverRegistration::STATUS_PENDING,
            'registration_payload' => array_merge($normalized, [
                'reused_existing_user_id' => $existing->id,
            ]),
        ]);

        return [
            'user' => $existing->fresh(['officialProfile.latestTerm', 'barangay']),
            'term' => $term,
            'registration' => $registration,
            'skip_setup_invite' => filled($existing->password),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeOfficerData(array $data, string $federationPosition): array
    {
        $suffix = $data['suffix'] ?? null;
        if ($suffix === 'NONE' || strtoupper(trim((string) $suffix)) === 'NONE') {
            $suffix = null;
        }

        return array_merge($data, [
            'role' => User::ROLE_SK_OFFICIAL,
            'position' => 'Chairperson',
            'federation_position' => $federationPosition,
            'term_status' => OfficialTerm::STATUS_INACTIVE,
            'status' => User::STATUS_INACTIVE,
            'municipality' => 'Santa Cruz',
            'province' => 'Laguna',
            'region' => 'IV-A CALABARZON',
            'suffix' => $suffix,
        ]);
    }

    /**
     * @param  array<string, mixed>  $presidentData
     * @param  array<string, mixed>  $vicePresidentData
     */
    private function assertNoDuplicateEmails(array $presidentData, array $vicePresidentData): void
    {
        $presidentEmail = strtolower(trim((string) ($presidentData['email'] ?? '')));
        $viceEmail = strtolower(trim((string) ($vicePresidentData['email'] ?? '')));

        if ($presidentEmail === '' || $viceEmail === '') {
            throw ValidationException::withMessages([
                'email' => 'Email is required for both officers.',
            ]);
        }

        if ($presidentEmail === $viceEmail) {
            throw ValidationException::withMessages([
                'email' => 'President and Vice President must use different email addresses.',
            ]);
        }
    }

    public function markAccountSetupCompleted(User $user): void
    {
        $registration = FederationTurnoverRegistration::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                FederationTurnoverRegistration::STATUS_PENDING,
                FederationTurnoverRegistration::STATUS_INVITED,
            ])
            ->latest('id')
            ->first();

        if ($registration === null) {
            return;
        }

        $registration->forceFill([
            'status' => FederationTurnoverRegistration::STATUS_ACCOUNT_CREATED,
            'account_setup_completed_at' => now(),
        ])->save();

        $keepOfficialsActive = filled($user->password)
            && $user->role === User::ROLE_SK_OFFICIAL;

        $user->forceFill([
            'turnover_status' => 'pending_confirmation',
            'account_status' => 'turnover_waiting',
            // Existing SK Official credentials remain usable on Officials portal.
            'status' => $keepOfficialsActive ? User::STATUS_ACTIVE : User::STATUS_INACTIVE,
        ])->save();

        $turnover = $registration->turnover()->with('registrations')->first();

        if ($turnover && $this->termDetectionService->bothIncomingOfficersReady($turnover)) {
            $turnover->forceFill([
                'status' => FederationTurnover::STATUS_PENDING_CONFIRMATION,
            ])->save();

            foreach ([$turnover->previousPresident, $turnover->previousVicePresident] as $outgoing) {
                if ($outgoing instanceof User) {
                    $this->notificationService->notifyUser(
                        $outgoing,
                        SkFederationsNotificationService::CATEGORY_GENERAL,
                        'Complete Federation Turnover',
                        'Both incoming Federation Officers have completed account setup. You may now transfer administrative access.',
                        route('dashboard'),
                    );
                }
            }
        }
    }
}
