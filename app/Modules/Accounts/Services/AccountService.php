<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Notifications\AccountResetPasswordNotification;
use App\Modules\Archive_Management\Services\TermRecordsArchiveService;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Authentication\Services\BootstrapSkFedAdminService;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(
        private readonly AuditLogInterface $auditLog,
        private readonly TermRecordsArchiveService $termRecordsArchiveService,
        private readonly ChairpersonFederationSyncService $chairpersonFederationSyncService,
        private readonly FederationRosterService $federationRosterService,
        private readonly SkOfficialRosterLimitsService $skOfficialRosterLimitsService,
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

        $emailsSent = 0;
        $emailFailed = [];

        foreach ($pendingEmails as $pending) {
            /** @var User $user */
            $user = $pending['user'];

            try {
                $this->sendInitialResetLink($user, $pending['role'], resetMailTransport: false);
                $emailsSent++;
            } catch (\Throwable $exception) {
                report($exception);

                $emailFailed[] = [
                    'row' => $pending['row'],
                    'email' => $user->email,
                    'message' => 'Account created but password setup email could not be sent.',
                ];
            }
        }

        if (method_exists(Mail::class, 'purgeSymfonyTransport')) {
            Mail::purgeSymfonyTransport();
        }

        return [
            'created' => $created,
            'failed' => $failed,
            'validation_errors' => $validationErrors,
            'emails_sent' => $emailsSent,
            'email_failed' => $emailFailed,
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
                $this->sendInitialResetLinkOrFail($user, $normalizedData['role'] ?? null);
            } catch (ValidationException $exception) {
                $this->rollbackCreatedAccount($user);
                throw $exception;
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

        $this->clearPasswordResetTokensForEmail($normalizedEmail);
    }

    private function clearInvitationStateForUser(User $user): void
    {
        $this->clearPasswordResetTokensForEmail((string) $user->email);

        if (Schema::hasTable('sk_official_email_verified_devices')) {
            DB::table('sk_official_email_verified_devices')->where('user_id', $user->id)->delete();
        }

        if (Schema::hasTable('sk_fed_email_verified_devices')) {
            DB::table('sk_fed_email_verified_devices')->where('user_id', $user->id)->delete();
        }
    }

    private function clearPasswordResetTokensForEmail(string $email): void
    {
        $normalizedEmail = strtolower(trim($email));

        if ($normalizedEmail === '') {
            return;
        }

        $table = (string) config('auth.passwords.users.table', 'password_reset_tokens');
        DB::table($table)->where('email', $normalizedEmail)->delete();
    }

    public function updateAccount(User $account, array $data, User $admin): User
    {
        $this->assertSameTenant($account->tenant_id, $admin->tenant_id, 'Target account is outside your tenant scope.');

        if ($account->isIncomingTurnoverOfficer()) {
            throw ValidationException::withMessages([
                'account' => 'Incoming turnover officers cannot be edited until federation turnover is completed.',
            ]);
        }

        $normalizedData = $this->withNormalizedMiddleName($data);

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

        return DB::transaction(function () use ($account, $normalizedData, $admin) {
            $account->forceFill([
                'name' => $this->buildFullName($normalizedData),
                'email' => $normalizedData['email'],
                'status' => $normalizedData['status'],
            ])->save();

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

            $latestTerm = $profile->terms()->latest('term_end')->first();
            $hasNewTermRange = ! $latestTerm
                || $latestTerm->term_start->toDateString() !== $normalizedData['term_start']
                || $latestTerm->term_end->toDateString() !== $normalizedData['term_end'];

            if ($hasNewTermRange) {
                if ($normalizedData['term_status'] === OfficialTerm::STATUS_ACTIVE) {
                    $activeTerms = $profile->terms()
                        ->where('status', OfficialTerm::STATUS_ACTIVE)
                        ->get();

                    foreach ($activeTerms as $activeTerm) {
                        $this->termRecordsArchiveService->archiveCompletedTerm($activeTerm, $admin);
                    }

                    $profile->terms()
                        ->where('status', OfficialTerm::STATUS_ACTIVE)
                        ->update(['status' => OfficialTerm::STATUS_INACTIVE]);
                }

                $this->createTermRecord($profile, [
                    'term_start' => $normalizedData['term_start'],
                    'term_end' => $normalizedData['term_end'],
                    'status' => $normalizedData['term_status'],
                ]);
            } elseif ($latestTerm->status !== $normalizedData['term_status']) {
                if ($normalizedData['term_status'] === OfficialTerm::STATUS_ACTIVE) {
                    $activeTerms = $profile->terms()
                        ->where('id', '!=', $latestTerm->id)
                        ->where('status', OfficialTerm::STATUS_ACTIVE)
                        ->get();

                    foreach ($activeTerms as $activeTerm) {
                        $this->termRecordsArchiveService->archiveCompletedTerm($activeTerm, $admin);
                    }

                    $profile->terms()
                        ->where('id', '!=', $latestTerm->id)
                        ->where('status', OfficialTerm::STATUS_ACTIVE)
                        ->update(['status' => OfficialTerm::STATUS_INACTIVE]);
                }

                if (in_array($normalizedData['term_status'], [OfficialTerm::STATUS_INACTIVE, OfficialTerm::STATUS_EXPIRED, OfficialTerm::STATUS_REPLACED], true)) {
                    $this->termRecordsArchiveService->archiveCompletedTerm($latestTerm->fresh(), $admin);
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
                ['email' => $account->email]
            );

            $fresh = $account->fresh(['officialProfile.terms', 'barangay']);

            if ($fresh->officialProfile) {
                $this->chairpersonFederationSyncService->syncForUser($fresh, (string) $fresh->officialProfile->position);
            }

            return $fresh;
        });
    }

    private function sendInitialResetLinkOrFail(User $user, ?string $role = null): void
    {
        try {
            $this->sendInitialResetLink($user, $role);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'target application URL is not configured')) {
                throw ValidationException::withMessages([
                    'email' => 'Password setup email could not be sent because the portal URL is not configured. Set SK_OFFICIALS_APP_URL (and SK_FED_APP_URL if needed) in .env, then run php artisan config:clear.',
                ]);
            }

            report($exception);

            throw ValidationException::withMessages([
                'email' => 'Unable to send password setup email. Please check mail settings and try again.',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => 'Unable to send password setup email. Please check mail settings and try again.',
            ]);
        }
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

    private function sendInitialResetLink(User $user, ?string $role = null, bool $resetMailTransport = true): void
    {
        $user->refresh();

        $this->clearPasswordResetTokensForEmail((string) $user->email);

        $token = Password::createToken($user);
        [$label, $baseUrl] = $this->resolvePasswordSetupTarget($user, $role);

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($label) || $label === '') {
            throw new \RuntimeException('Password setup email could not be sent because the target application URL is not configured.');
        }

        Notification::sendNow(
            $user,
            new AccountResetPasswordNotification(
                $token,
                $baseUrl,
                $label,
                ($role ?? $user->role) === User::ROLE_SK_OFFICIAL,
            )
        );

        if ($resetMailTransport && method_exists(Mail::class, 'purgeSymfonyTransport')) {
            Mail::purgeSymfonyTransport();
        }

        Log::info('Password setup email sent.', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $role ?? $user->role,
            'target' => $baseUrl,
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolvePasswordSetupTarget(User $user, ?string $role = null): array
    {
        $role = $role ?? $user->role;

        if ($role === User::ROLE_SK_OFFICIAL) {
            $baseUrl = trim((string) config('services.sk_officials_app_url'));

            return ['SK Official', $baseUrl !== '' ? $baseUrl : null];
        }

        if ($role === User::ROLE_SK_FED) {
            $baseUrl = trim((string) config('services.sk_fed_app_url'));

            return ['SK Federation', $baseUrl !== '' ? $baseUrl : null];
        }

        return [null, null];
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
            ['email' => $target->email, 'role' => $target->role]
        );
    }

    public function restoreAccount(User $target, User $admin): User
    {
        $this->assertSameTenant($target->tenant_id, $admin->tenant_id, 'Target account is outside your tenant scope.');

        if (! $target->trashed()) {
            throw ValidationException::withMessages([
                'account' => 'This account is not in the deleted archive.',
            ]);
        }

        $target->restore();
        $target->forceFill([
            'status' => User::STATUS_ACTIVE,
        ])->save();

        $this->logAuditAction(
            $admin,
            'account_restored',
            'users',
            (string) $target->id,
            ['email' => $target->email, 'role' => $target->role]
        );

        return $target->fresh(['officialProfile.terms', 'barangay']);
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
                $this->termRecordsArchiveService->archiveCompletedTerm($activeTerm, $admin);
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
        $middleInitial = $this->deriveMiddleInitial(
            $this->normalizeMiddleName($data['middle_name'] ?? null)
        );

        return trim(implode(' ', array_filter([
            $data['first_name'] ?? null,
            $middleInitial,
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

    protected function normalizeMiddleName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $middleName = trim($value);

        return $middleName === '' ? null : $middleName;
    }

    protected function deriveMiddleInitial(?string $middleName): ?string
    {
        if ($middleName === null) {
            return null;
        }

        if (! preg_match('/[A-Za-z]/', $middleName, $matches)) {
            return null;
        }

        return strtoupper($matches[0]).'.';
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
        $rawAge = $data['age'] ?? null;
        if (is_numeric($rawAge)) {
            return (int) $rawAge;
        }

        $dateOfBirth = isset($data['date_of_birth']) ? (string) $data['date_of_birth'] : null;

        return $this->deriveAge($dateOfBirth);
    }
}
