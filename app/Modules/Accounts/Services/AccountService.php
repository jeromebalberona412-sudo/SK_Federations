<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Support\SkOfficialTermDates;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Authentication\Services\BootstrapSkFedAdminService;
use App\Modules\SK_Federation_Records\Services\SkFederationRecordsService;
use App\Modules\SK_Officials_Records\Services\SkOfficialsRecordsService;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(
        private readonly AuditLogInterface $auditLog,
        private readonly SkOfficialsRecordsService $termRecordsArchiveService,
        private readonly SkFederationRecordsService $federationRecordsArchiveService,
        private readonly ChairpersonFederationSyncService $chairpersonFederationSyncService,
        private readonly FederationRosterService $federationRosterService,
        private readonly SkOfficialRosterLimitsService $skOfficialRosterLimitsService,
        private readonly AccountSetupService $accountSetupService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     created: int,
     *     failed: list<array{row: int, email: ?string, message: string}>,
     *     validation_errors: list<array{row: int, error: string}>,
     *     emails_sent: int,
     *     email_failed: list<array{row: int, email: ?string, message: string}>
     * }
     */
    public function batchCreateAccounts(array $rows, string $role, User $admin): array
    {
        set_time_limit(0);

        $importService = new BatchAccountImportService((int) $admin->tenant_id);
        $created = 0;
        $failed = [];
        $validationErrors = [];
        $seenEmails = [];
        $pendingEmails = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $email = is_array($row) ? strtolower((string) ($row['email'] ?? $row['email address'] ?? '')) : '';

            if ($email !== '' && isset($seenEmails[$email])) {
                $message = 'Duplicate email in upload file.';
                $failed[] = ['row' => $rowNumber, 'email' => $email, 'message' => $message];
                $validationErrors[] = ['row' => $rowNumber, 'error' => $message];

                continue;
            }

            if ($email !== '') {
                $seenEmails[$email] = true;
            }

            try {
                $normalized = $importService->normalizeAccountRow($row, $role, true);

                if (User::query()->where('email', $normalized['email'])->whereNull('deleted_at')->exists()) {
                    $message = 'Email is already registered.';
                    $failed[] = ['row' => $rowNumber, 'email' => $normalized['email'], 'message' => $message];
                    $validationErrors[] = ['row' => $rowNumber, 'error' => $message];

                    continue;
                }

                $this->purgeSoftDeletedUserByEmail($normalized['email'], (int) $admin->tenant_id);

                $accountData = array_merge($normalized, ['role' => $role]);
                $user = $this->createAccount($accountData, $admin, sendPasswordEmail: false);
                $pendingEmails[] = [
                    'row' => $rowNumber,
                    'user' => $user,
                    'role' => $role,
                    'email' => strtolower(trim((string) $user->email)),
                ];
                $created++;
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?? 'Validation failed.';
                $failed[] = [
                    'row' => $rowNumber,
                    'email' => $email !== '' ? $email : null,
                    'message' => $message,
                ];
                $validationErrors[] = ['row' => $rowNumber, 'error' => $message];
            } catch (QueryException $exception) {
                $message = str_contains(strtolower($exception->getMessage()), 'duplicate')
                    ? 'Email is already registered.'
                    : 'Database error while creating this account.';

                $failed[] = [
                    'row' => $rowNumber,
                    'email' => $email !== '' ? $email : null,
                    'message' => $message,
                ];
                $validationErrors[] = ['row' => $rowNumber, 'error' => $message];
            } catch (\Throwable $exception) {
                report($exception);

                $message = $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to create account for this row.';

                $failed[] = [
                    'row' => $rowNumber,
                    'email' => $email !== '' ? $email : null,
                    'message' => $message,
                ];
                $validationErrors[] = ['row' => $rowNumber, 'error' => $message];
            }
        }

        $emailsQueued = 0;
        $emailFailed = [];
        $pendingEmailJobs = [];

        foreach ($pendingEmails as $pending) {
            /** @var User $user */
            $user = $pending['user'];
            $pendingEmailJobs[] = [
                'user_id' => (int) $user->id,
                'role' => $pending['role'],
                'email' => strtolower(trim((string) ($pending['email'] ?? $user->email))),
                'row' => $pending['row'],
            ];
            $emailsQueued++;
        }

        if ($pendingEmailJobs !== []) {
            $this->accountSetupService->queueBatchSetupEmailsAfterResponse($pendingEmailJobs);
        }

        return [
            'created' => $created,
            'failed' => $failed,
            'validation_errors' => $validationErrors,
            'emails_sent' => 0,
            'emails_queued' => $emailsQueued,
            'email_failed' => $emailFailed,
            'created_user_ids' => array_values(array_map(
                static fn (array $pending): int => (int) $pending['user']->id,
                $pendingEmails
            )),
        ];
    }

    /**
     * @param  list<int>  $accountIds
     * @return array{deleted: int, failed: list<array{id: int, message: string}>}
     */
    public function bulkDeactivate(array $accountIds, User $admin): array
    {
        $deleted = 0;
        $failed = [];

        foreach ($accountIds as $accountId) {
            $target = User::query()
                ->where('id', $accountId)
                ->where('tenant_id', $admin->tenant_id)
                ->first();

            if (! $target) {
                $failed[] = ['id' => $accountId, 'message' => 'Account not found.'];

                continue;
            }

            try {
                $this->deactivate($target, $admin);
                $deleted++;
            } catch (ValidationException $exception) {
                $failed[] = [
                    'id' => $accountId,
                    'message' => collect($exception->errors())->flatten()->first() ?? 'Unable to delete account.',
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    public function createAccount(array $data, User $admin, bool $sendPasswordEmail = true): User
    {
        $normalizedData = $this->withNormalizedMiddleName($data);
        $shouldSendReset = empty($normalizedData['password']);
        $email = strtolower(trim((string) $normalizedData['email']));

        if (User::query()->where('email', $email)->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email is already registered.',
            ]);
        }

        $this->purgeSoftDeletedUserByEmail($email, (int) $admin->tenant_id);

        if (($normalizedData['role'] ?? '') === User::ROLE_SK_FED) {
            throw ValidationException::withMessages([
                'role' => 'Federation roster members are added automatically when an SK Chairperson is created in SK Officials.',
            ]);
        }

        if (($normalizedData['role'] ?? '') === User::ROLE_SK_OFFICIAL) {
            $barangayId = (int) $normalizedData['barangay_id'];
            $position = (string) $normalizedData['position'];
            $presidentTerms = SkOfficialTermDates::presidentTermDates((int) $admin->tenant_id);
            if ($presidentTerms !== null) {
                $normalizedData['term_start'] = $presidentTerms['term_start'];
                $normalizedData['term_end'] = $presidentTerms['term_end'];
            }

            $this->skOfficialRosterLimitsService->assertRosterLimits(
                (int) $admin->tenant_id,
                $barangayId,
                $position,
                null,
                (string) ($normalizedData['term_start'] ?? ''),
                (string) ($normalizedData['term_end'] ?? ''),
            );
        }

        if ($shouldSendReset) {
            $normalizedData['status'] = User::STATUS_PENDING_APPROVAL;
        }

        $user = DB::transaction(function () use ($normalizedData, $admin) {
            $user = $this->createUser($normalizedData, $admin);
            $this->assignRole($user, $normalizedData['role']);
            $this->assignBarangay($user, (int) $normalizedData['barangay_id']);

            $profile = $this->createOfficialProfile($user, $normalizedData);
            $this->createTermRecord($profile, [
                'term_start' => $normalizedData['term_start'],
                'term_end' => $normalizedData['term_end'],
                'status' => $normalizedData['term_status'],
            ]);

            return $user->fresh(['officialProfile.terms', 'barangay']);
        });

        if ($shouldSendReset && $sendPasswordEmail) {
            try {
                // Send immediately (sendNow) so setup mail is not lost when no queue worker runs.
                $this->accountSetupService->sendSetupEmailOrFail($user, $normalizedData['role'] ?? null);
            } catch (ValidationException $exception) {
                // Account already exists — keep it and let the admin resend from the actions menu.
                Log::error('Account setup email sending failed', [
                    'operation' => 'create_account_setup',
                    'user_id' => $user->id,
                    'exception' => ValidationException::class,
                    'message' => collect($exception->errors())->flatten()->first(),
                ]);

                throw ValidationException::withMessages([
                    'email' => 'Account was created, but the account setup email could not be sent. Please use Resend Account Setup Email.',
                ]);
            }
        }

        $this->logAuditAction(
            $admin,
            'account_created',
            'users',
            (string) $user->id,
            ['role' => $normalizedData['role'], 'email' => $user->email]
        );

        if ($user->officialProfile) {
            $this->chairpersonFederationSyncService->syncForUser($user, (string) $user->officialProfile->position);
        }

        return $user;
    }

    private function rollbackCreatedAccount(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->clearInvitationStateForUser($user);

            $profile = OfficialProfile::query()->where('user_id', $user->id)->first();

            if ($profile) {
                $profile->terms()->delete();
                $profile->delete();
            }

            $user->forceDelete();
        });
    }

    private function purgeSoftDeletedUserByEmail(string $email, int $tenantId): void
    {
        $normalizedEmail = strtolower(trim($email));

        $existingUsers = User::withTrashed()
            ->where('email', $normalizedEmail)
            ->where('tenant_id', $tenantId)
            ->whereNotNull('deleted_at')
            ->get();

        foreach ($existingUsers as $existing) {
            $this->rollbackCreatedAccount($existing);
        }

        $this->accountSetupService->invalidateTokensForEmail($normalizedEmail);
    }

    private function clearInvitationStateForUser(User $user): void
    {
        $this->accountSetupService->invalidateTokensForUser($user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{account: User, email_changed: bool, setup_email_sent: bool, setup_email_error: ?string}
     */
    public function updateAccount(User $account, array $data, User $admin): array
    {
        $this->assertSameTenant($account->tenant_id, $admin->tenant_id, 'Target account is outside your tenant scope.');

        if ($account->isIncomingTurnoverOfficer()) {
            throw ValidationException::withMessages([
                'account' => 'Incoming turnover officers cannot be edited until federation turnover is completed.',
            ]);
        }

        $normalizedData = $this->withNormalizedMiddleName($data);
        $previousEmail = strtolower(trim((string) $account->email));
        $newEmail = strtolower(trim((string) $normalizedData['email']));
        $emailChanged = $previousEmail !== $newEmail;

        $account->loadMissing('officialProfile');
        $isPresident = $account->role === User::ROLE_SK_OFFICIAL
            && strcasecmp(trim((string) ($account->officialProfile?->federation_position ?? '')), 'President') === 0;
        if ($account->role === User::ROLE_SK_OFFICIAL && ! $isPresident) {
            $presidentTerms = SkOfficialTermDates::presidentTermDates((int) $admin->tenant_id);
            if ($presidentTerms !== null) {
                $normalizedData['term_start'] = $presidentTerms['term_start'];
                $normalizedData['term_end'] = $presidentTerms['term_end'];
            }
        }

        $fresh = DB::transaction(function () use ($account, $normalizedData, $admin, $previousEmail, $newEmail, $emailChanged) {
            if ($account->role === User::ROLE_SK_OFFICIAL) {
                $this->skOfficialRosterLimitsService->assertRosterLimits(
                    (int) $admin->tenant_id,
                    (int) ($normalizedData['barangay_id'] ?? $account->barangay_id),
                    (string) ($normalizedData['position'] ?? $account->officialProfile?->position ?? ''),
                    (int) $account->id,
                    (string) ($normalizedData['term_start'] ?? ''),
                    (string) ($normalizedData['term_end'] ?? ''),
                );
            }

            $account->forceFill([
                'name' => $this->buildFullName($normalizedData),
                'email' => $newEmail,
                'status' => $emailChanged
                    ? User::STATUS_PENDING_APPROVAL
                    : $normalizedData['status'],
            ])->save();

            if ($emailChanged) {
                $this->accountSetupService->prepareAccountForEmailChange($account->fresh(), $previousEmail);
            }

            $this->assignBarangay($account, (int) $normalizedData['barangay_id']);

            $profile = $account->officialProfile;
            if (! $profile) {
                $profile = $this->createOfficialProfile($account, $normalizedData);
            } else {
                $profileUpdate = [
                    'first_name' => $normalizedData['first_name'],
                    'last_name' => $normalizedData['last_name'],
                    'middle_name' => $normalizedData['middle_name'] ?? null,
                    'suffix' => $normalizedData['suffix'] ?? null,
                    'sex' => $normalizedData['sex'] ?? null,
                    'date_of_birth' => $normalizedData['date_of_birth'] ?? null,
                    'age' => $this->resolveStoredAge($normalizedData),
                    'contact_number' => $normalizedData['contact_number'] ?? null,
                    'municipality' => 'Santa Cruz',
                    'province' => 'Laguna',
                    'region' => 'IV-A CALABARZON',
                ];

                if ($account->role === User::ROLE_SK_FED) {
                    $profileUpdate['position'] = $normalizedData['position'];
                    $profileUpdate['federation_position'] = $normalizedData['federation_position']
                        ?? $normalizedData['position'];
                } else {
                    if (array_key_exists('position', $normalizedData) && $normalizedData['position'] !== '') {
                        $profileUpdate['position'] = $normalizedData['position'];
                    }

                    if (array_key_exists('federation_position', $normalizedData)) {
                        $profileUpdate['federation_position'] = $normalizedData['federation_position'] ?: null;
                    }
                }

                $profile->update($profileUpdate);
            }

            $latestTerm = $profile->terms()->latest('id')->first();
            $hasNewTermRange = ! $latestTerm
                || $latestTerm->term_start->toDateString() !== $normalizedData['term_start']
                || $latestTerm->term_end->toDateString() !== $normalizedData['term_end'];

            if ($hasNewTermRange) {
                if ($latestTerm) {
                    // Preserve Previous Terms history before overwriting live dates in place.
                    // (Direct SQL updates to ACTIVE terms were wiping history when snapshots were refreshed.)
                    $this->archiveTermSnapshotsIfHistorical($latestTerm, $admin);

                    // Edit Account should update the current term in place so the table
                    // reflects the new dates immediately (no stale "latest by term_end" row).
                    if ($normalizedData['term_status'] === OfficialTerm::STATUS_ACTIVE) {
                        $profile->terms()
                            ->where('id', '!=', $latestTerm->id)
                            ->where('status', OfficialTerm::STATUS_ACTIVE)
                            ->update(['status' => OfficialTerm::STATUS_INACTIVE]);
                    }

                    $latestTerm->forceFill([
                        'term_start' => $normalizedData['term_start'],
                        'term_end' => $normalizedData['term_end'],
                        'status' => $normalizedData['term_status'],
                    ])->save();
                } else {
                    $this->createTermRecord($profile, [
                        'term_start' => $normalizedData['term_start'],
                        'term_end' => $normalizedData['term_end'],
                        'status' => $normalizedData['term_status'],
                    ]);
                }
            } elseif ($latestTerm->status !== $normalizedData['term_status']) {
                if ($normalizedData['term_status'] === OfficialTerm::STATUS_ACTIVE) {
                    $activeTerms = $profile->terms()
                        ->where('id', '!=', $latestTerm->id)
                        ->where('status', OfficialTerm::STATUS_ACTIVE)
                        ->get();

                    foreach ($activeTerms as $activeTerm) {
                        $this->archiveTermToRecords($activeTerm, $admin);
                    }

                    $profile->terms()
                        ->where('id', '!=', $latestTerm->id)
                        ->where('status', OfficialTerm::STATUS_ACTIVE)
                        ->update(['status' => OfficialTerm::STATUS_INACTIVE]);
                }

                if (in_array($normalizedData['term_status'], [OfficialTerm::STATUS_INACTIVE, OfficialTerm::STATUS_EXPIRED, OfficialTerm::STATUS_REPLACED], true)) {
                    $this->archiveTermToRecords($latestTerm->fresh(), $admin);
                }

                $latestTerm->update([
                    'status' => $normalizedData['term_status'],
                ]);
            }

            $this->logAuditAction(
                $admin,
                'account_updated',
                'users',
                (string) $account->id,
                [
                    'email' => $newEmail,
                    'email_changed' => $emailChanged,
                    'previous_email_changed' => $emailChanged,
                ]
            );

            $updated = $account->fresh(['officialProfile.terms', 'barangay']);

            if ($updated->officialProfile) {
                $this->chairpersonFederationSyncService->syncForUser($updated, (string) $updated->officialProfile->position);
            }

            return $updated;
        });

        $officialTermsSynced = 0;
        $leadershipTerms = null;
        $presidentPosition = trim((string) ($fresh->officialProfile?->federation_position ?? ''));
        if (strcasecmp($presidentPosition, 'President') === 0) {
            $fresh->loadMissing('officialProfile.latestTerm');
            $presidentTerm = $fresh->officialProfile?->latestTerm;
            if ($presidentTerm?->term_start && $presidentTerm?->term_end) {
                $termStart = $presidentTerm->term_start->toDateString();
                $termEnd = $presidentTerm->term_end->toDateString();
                $leadershipTerms = [
                    'term_start' => $termStart,
                    'term_end' => $termEnd,
                ];
                $officialTermsSynced = $this->syncSkOfficialTermsFromLeadership(
                    (int) $admin->tenant_id,
                    $termStart,
                    $termEnd,
                    (int) $fresh->id,
                );
            }
        }

        $setupEmailSent = false;
        $setupEmailError = null;

        if ($emailChanged) {
            try {
                // Auto-resend account setup / activation email to the NEW email only.
                $this->accountSetupService->sendSetupEmailAfterEmailChange($fresh, $previousEmail);
                $setupEmailSent = true;
            } catch (ValidationException $exception) {
                $setupEmailError = collect($exception->errors())->flatten()->first()
                    ?: AccountSetupService::EMAIL_CHANGED_FAILED_MESSAGE;
                Log::error('Account setup email sending failed', [
                    'operation' => 'email_change_setup',
                    'user_id' => $fresh->id,
                    'exception' => ValidationException::class,
                    'message' => $setupEmailError,
                ]);
            } catch (\Throwable $exception) {
                report($exception);
                $setupEmailError = AccountSetupService::EMAIL_CHANGED_FAILED_MESSAGE;
                Log::error('Account setup email sending failed', [
                    'operation' => 'email_change_setup',
                    'user_id' => $fresh->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'account' => $fresh->fresh(['officialProfile.terms', 'barangay', 'officialProfile.latestTerm']),
            'email_changed' => $emailChanged,
            'setup_email_sent' => $setupEmailSent,
            'setup_email_error' => $setupEmailError,
            'official_terms_synced' => $officialTermsSynced,
            'leadership_terms' => $leadershipTerms,
        ];
    }

    public function resendAccountSetupEmail(User $target, User $admin): void
    {
        $this->assertSameTenant($target->tenant_id, $admin->tenant_id, 'Target account is outside your tenant scope.');

        if ($target->isIncomingTurnoverOfficer()) {
            throw ValidationException::withMessages([
                'account' => 'Incoming turnover officers cannot receive setup emails until federation turnover is completed.',
            ]);
        }

        $target->refresh();

        if ($this->accountSetupService->isAccountAlreadyActivated($target)
            || ! $this->accountSetupService->needsAccountSetup($target)) {
            throw ValidationException::withMessages([
                'account' => AccountSetupService::ALREADY_ACTIVATED_MESSAGE,
            ]);
        }

        $this->accountSetupService->resendSetupEmail($target, $admin);

        $this->logAuditAction(
            $admin,
            'account_setup_email_resent',
            'users',
            (string) $target->id,
            ['email' => $target->email, 'role' => $target->role]
        );
    }

    public function createUser(array $data, User $admin): User
    {
        $fullName = $this->buildFullName($data);
        $password = $data['password'] ?? '';

        if ($password === '') {
            $password = Str::random(16);
        }

        // Postgres can be strict about boolean literals when the query
        // builder substitutes values. To avoid datatype mismatch errors
        // (seen when a boolean column receives an integer literal), emit
        // a database boolean literal for Postgres connections.
        $defaultMustChange = true;
        $driver = config('database.connections.'.config('database.default').'.driver');

        if ($driver === 'pgsql') {
            $defaultMustChange = DB::raw('true');
        }

        return User::create([
            'tenant_id' => $admin->tenant_id,
            'name' => $fullName,
            'email' => strtolower(trim((string) $data['email'])),
            'password' => $password,
            'role' => User::ROLE_USER,
            'status' => $data['status'] ?? User::STATUS_PENDING_APPROVAL,
            'must_change_password' => $defaultMustChange,
        ]);
    }

    public function assignRole(User $user, string $role): void
    {
        $user->forceFill(['role' => $role])->save();
    }

    public function createOfficialProfile(User $user, array $data): OfficialProfile
    {
        $role = $data['role'] ?? $user->role;
        $position = $data['position'];
        $federationPosition = null;

        if ($role === User::ROLE_SK_FED) {
            $federationPosition = $position;
        }

        return OfficialProfile::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'suffix' => $data['suffix'] ?? null,
            'sex' => $data['sex'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'age' => $this->resolveStoredAge($data),
            'contact_number' => $data['contact_number'] ?? null,
            'position' => $position,
            'federation_position' => $federationPosition,
            'municipality' => 'Santa Cruz',
            'province' => 'Laguna',
            'region' => 'IV-A CALABARZON',
        ]);
    }

    public function createTermRecord(OfficialProfile $profile, array $data): OfficialTerm
    {
        return OfficialTerm::create([
            'official_profile_id' => $profile->id,
            'term_start' => $data['term_start'],
            'term_end' => $data['term_end'],
            'status' => $data['status'],
        ]);
    }

    /**
     * Apply SK Fed President term dates to every other SK Official in the tenant.
     */
    public function syncSkOfficialTermsFromLeadership(
        int $tenantId,
        string $termStart,
        string $termEnd,
        ?int $exceptUserId = null,
    ): int {
        if ($tenantId <= 0 || $termStart === '' || $termEnd === '') {
            return 0;
        }

        $synced = 0;

        User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->whereNull('deleted_at')
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->with(['officialProfile.latestTerm'])
            ->orderBy('id')
            ->each(function (User $official) use ($termStart, $termEnd, &$synced): void {
                $profile = $official->officialProfile;
                if (! $profile) {
                    return;
                }

                if (strcasecmp(trim((string) ($profile->federation_position ?? '')), 'President') === 0) {
                    return;
                }

                $latestTerm = $profile->latestTerm;
                if ($latestTerm) {
                    $currentStart = optional($latestTerm->term_start)->toDateString();
                    $currentEnd = optional($latestTerm->term_end)->toDateString();
                    if ($currentStart === $termStart && $currentEnd === $termEnd) {
                        return;
                    }

                    $latestTerm->forceFill([
                        'term_start' => $termStart,
                        'term_end' => $termEnd,
                    ])->save();
                    $synced++;

                    return;
                }

                $this->createTermRecord($profile, [
                    'term_start' => $termStart,
                    'term_end' => $termEnd,
                    'status' => OfficialTerm::STATUS_ACTIVE,
                ]);
                $synced++;
            });

        return $synced;
    }

    public function assignBarangay(User $user, int $barangayId): void
    {
        $barangay = Barangay::query()
            ->where('id', $barangayId)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (! $barangay) {
            throw ValidationException::withMessages([
                'barangay_id' => 'Selected barangay does not belong to your tenant.',
            ]);
        }

        $user->forceFill([
            'barangay_id' => $barangayId,
        ])->save();
    }

    public function deactivate(User $target, User $admin): void
    {
        $this->assertSameTenant($target->tenant_id, $admin->tenant_id, 'Target account is outside your tenant scope.');

        if (Str::lower((string) $target->email) === BootstrapSkFedAdminService::bootstrapEmailNormalized()) {
            throw ValidationException::withMessages([
                'account' => 'The SK Federation Administrator account cannot be deactivated.',
            ]);
        }

        if ($target->is($admin)) {
            throw ValidationException::withMessages([
                'account' => 'You cannot deactivate your own admin account.',
            ]);
        }

        if ($target->isIncomingTurnoverOfficer()) {
            throw ValidationException::withMessages([
                'account' => 'Incoming turnover officers cannot be deleted until federation turnover is completed.',
            ]);
        }

        DB::transaction(function () use ($target, $admin) {
            $target->loadMissing(['officialProfile.latestTerm', 'barangay']);

            // Archive snapshot MUST succeed before removing from the active list.
            if ($target->role === User::ROLE_SK_OFFICIAL) {
                app(\App\Modules\Deleted_SK_Officials\Services\DeletedSkOfficialsService::class)
                    ->archiveOfficial($target, $admin);
            }

            $target->forceFill([
                'status' => User::STATUS_INACTIVE,
            ])->save();
            $target->delete();

            $this->clearInvitationStateForUser($target);

            $this->logAuditAction(
                $admin,
                'account_deactivated',
                'users',
                (string) $target->id,
                [
                    'email' => $target->email,
                    'role' => $target->role,
                    'archived' => $target->role === User::ROLE_SK_OFFICIAL,
                ]
            );
        });
    }

    public function restoreAccount(User $target, User $admin): User
    {
        $this->assertSameTenant($target->tenant_id, $admin->tenant_id, 'Target account is outside your tenant scope.');

        if (! $target->trashed()) {
            throw ValidationException::withMessages([
                'account' => 'This account is not in the deleted archive.',
            ]);
        }

        return DB::transaction(function () use ($target, $admin) {
            $target->restore();
            $target->forceFill([
                'status' => User::STATUS_ACTIVE,
            ])->save();

            if ($target->role === User::ROLE_SK_OFFICIAL) {
                \App\Modules\Deleted_SK_Officials\Models\DeletedSkOfficial::query()
                    ->where('original_user_id', $target->id)
                    ->whereNull('restored_at')
                    ->update(['restored_at' => now()]);
            }

            $this->logAuditAction(
                $admin,
                'account_restored',
                'users',
                (string) $target->id,
                ['email' => $target->email, 'role' => $target->role]
            );

            return $target->fresh(['officialProfile.terms', 'barangay']);
        });
    }

    /**
     * Permanently remove a soft-deleted account and related official profile/terms.
     */
    public function permanentlyDeleteTrashedAccount(User $target): void
    {
        if (! $target->trashed()) {
            throw ValidationException::withMessages([
                'account' => 'Only deleted archive accounts can be permanently removed.',
            ]);
        }

        $this->rollbackCreatedAccount($target);
    }

    public function resetPassword(User $target, string $newPassword, User $admin): void
    {
        $this->assertSameTenant($target->tenant_id, $admin->tenant_id, 'Target account is outside your tenant scope.');

        if ($target->is($admin)) {
            throw ValidationException::withMessages([
                'account' => 'You cannot reset your own password from this action.',
            ]);
        }

        $driver = config('database.connections.'.config('database.default').'.driver');
        $mustChange = true;

        if ($driver === 'pgsql') {
            $mustChange = DB::raw('true');
        }

        $target->forceFill([
            'password' => $newPassword,
            'must_change_password' => $mustChange,
        ])->save();

        $this->logAuditAction(
            $admin,
            'password_reset',
            'users',
            (string) $target->id,
            ['email' => $target->email]
        );
    }

    public function extendTerm(OfficialProfile $profile, array $data, User $admin): OfficialTerm
    {
        $this->assertSameTenant($profile->tenant_id, $admin->tenant_id, 'Target profile is outside your tenant scope.');

        return DB::transaction(function () use ($profile, $data, $admin) {
            $activeTerms = $profile->terms()
                ->where('status', OfficialTerm::STATUS_ACTIVE)
                ->get();

            foreach ($activeTerms as $activeTerm) {
                $this->archiveTermToRecords($activeTerm, $admin);
            }

            $profile->terms()
                ->where('status', OfficialTerm::STATUS_ACTIVE)
                ->update(['status' => OfficialTerm::STATUS_INACTIVE]);

            $term = $this->createTermRecord($profile, [
                'term_start' => $data['term_start'],
                'term_end' => $data['term_end'],
                'status' => $data['status'],
            ]);

            $this->logAuditAction(
                $admin,
                'term_extended',
                'official_profiles',
                (string) $profile->id,
                [
                    'user_id' => $profile->user_id,
                    'term_start' => $term->term_start->toDateString(),
                    'term_end' => $term->term_end->toDateString(),
                    'status' => $term->status,
                ]
            );

            return $term;
        });
    }

    public function logAuditAction(User $user, string $action, string $entityType, ?string $entityId = null, array $metadata = []): void
    {
        $this->auditLog->log('accounts.'.$action, $user, array_merge([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ], $metadata));
    }

    protected function assertSameTenant(?int $targetTenantId, ?int $actorTenantId, string $message): void
    {
        if (! $targetTenantId || ! $actorTenantId || $targetTenantId !== $actorTenantId) {
            throw ValidationException::withMessages([
                'tenant' => $message,
            ]);
        }
    }

    protected function buildFullName(array $data): string
    {
        $middleName = $this->normalizePersonName(
            $this->normalizeMiddleName($data['middle_name'] ?? null)
        );

        return trim(implode(' ', array_filter([
            $data['first_name'] ?? null,
            $middleName,
            $data['last_name'] ?? null,
            $data['suffix'] ?? null,
        ])));
    }

    protected function withNormalizedMiddleName(array $data): array
    {
        $middleName = $this->normalizeMiddleName($data['middle_name'] ?? ($data['middle_initial'] ?? null));
        $data['first_name'] = $this->normalizePersonName($data['first_name'] ?? null);
        $data['last_name'] = $this->normalizePersonName($data['last_name'] ?? null);
        $data['middle_name'] = $this->normalizePersonName($middleName);
        if (($data['suffix'] ?? null) === 'NONE') {
            $data['suffix'] = null;
        }
        unset($data['middle_initial']);

        return $data;
    }

    protected function normalizePersonName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_strtoupper($trimmed, 'UTF-8');
    }

    protected function archiveTermToRecords(?OfficialTerm $term, ?User $admin): void
    {
        if ($term === null) {
            return;
        }

        $this->termRecordsArchiveService->archiveCompletedTerm($term, $admin);
        $this->federationRecordsArchiveService->archiveCompletedTerm($term, $admin);
    }

    /**
     * Snapshot an ended / replaced term into Records before live dates are overwritten.
     */
    protected function archiveTermSnapshotsIfHistorical(OfficialTerm $term, ?User $admin): void
    {
        $termEnd = $term->term_end;
        $wasEnded = $termEnd !== null && $termEnd->copy()->startOfDay()->lt(now()->startOfDay());
        $wasHistoricalStatus = in_array((string) $term->status, [
            OfficialTerm::STATUS_EXPIRED,
            OfficialTerm::STATUS_REPLACED,
            OfficialTerm::STATUS_INACTIVE,
        ], true);

        if (! $wasEnded && ! $wasHistoricalStatus) {
            return;
        }

        $this->archiveTermToRecords($term, $admin);
    }

    protected function normalizeMiddleName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $middleName = trim($value);

        return $middleName === '' ? null : $middleName;
    }

    protected function deriveAge(?string $dateOfBirth): ?int
    {
        if (! $dateOfBirth) {
            return null;
        }

        return Carbon::parse($dateOfBirth)->age;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveStoredAge(array $data): ?int
    {
        $dateOfBirth = isset($data['date_of_birth']) ? (string) $data['date_of_birth'] : null;
        $fromDob = $this->deriveAge($dateOfBirth);
        if ($fromDob !== null) {
            return $fromDob;
        }

        $rawAge = $data['age'] ?? null;
        if (is_numeric($rawAge)) {
            return (int) $rawAge;
        }

        return null;
    }
}
