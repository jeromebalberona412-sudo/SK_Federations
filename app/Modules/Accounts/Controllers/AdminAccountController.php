<?php

namespace App\Modules\Accounts\Controllers;

use App\Modules\Accounts\Database\Seeders\BarangaySeeder;
use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Requests\AssignFederationPositionRequest;
use App\Modules\Accounts\Requests\BatchStoreAccountsRequest;
use App\Modules\Accounts\Requests\BulkDeactivateAccountsRequest;
use App\Modules\Accounts\Requests\ExtendTermRequest;
use App\Modules\Accounts\Requests\StoreAccountRequest;
use App\Modules\Accounts\Requests\UpdateAccountRequest;
use App\Modules\Accounts\Services\AccountBatchTemplateService;
use App\Modules\Accounts\Services\AccountService;
use App\Modules\Accounts\Services\AccountSetupService;
use App\Modules\Accounts\Services\BatchAccountImportService;
use App\Modules\Accounts\Services\FederationRosterService;
use App\Modules\Accounts\Support\SkOfficialTermDates;
use App\Modules\SK_Officials_Records\Services\ExpiredTermProcessorService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use App\Services\SkFedPageCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AdminAccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly ExpiredTermProcessorService $expiredTermProcessor,
        private readonly AccountBatchTemplateService $batchTemplateService,
        private readonly FederationRosterService $federationRosterService,
    ) {}

    public function indexFederation(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $tenantId = $this->resolveTenantId($request->user());
        $this->ensureTenantBarangays($tenantId);
        $this->scheduleExpiredTermProcessing($tenantId, $request->user());
        $this->federationRosterService->syncFederationRosterAccess($tenantId);

        // Live DB read — do not cache roster rows (stale cache showed data ≠ users table).
        $query = $this->federationRosterService->federationRosterQuery($tenantId);

        $accounts = $query->get()
            ->sortBy(fn (User $account) => Str::lower($account->barangay?->name ?? 'zzzz'))
            ->values();
        $barangays = Barangay::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $takenFederationPositions = $this->federationRosterService->takenFederationPositions($tenantId);

        $accountType = 'sk_federation';
        $positionOptions = OfficialProfile::federationPositionOptions();

        $leadershipTermDefaults = SkOfficialTermDates::leadershipDefaults($tenantId);
        $defaultTermStart = $leadershipTermDefaults['term_start'];
        $defaultTermEnd = $leadershipTermDefaults['term_end'];

        return view('accounts::manage_account', compact(
            'accounts',
            'accountType',
            'barangays',
            'positionOptions',
            'takenFederationPositions',
            'defaultTermStart',
            'defaultTermEnd'
        ));
    }

    public function indexOfficials(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $tenantId = $this->resolveTenantId($request->user());
        $this->ensureTenantBarangays($tenantId);

        // Do not block page render on expired-term archival; run once per short window after response.
        $this->scheduleExpiredTermProcessing($tenantId, $request->user());

        // Keep the page shell fast on Hostinger — table rows load via AJAX (/accounts/officials/data).
        $barangays = Barangay::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
        $accounts = collect();

        $accountType = 'sk_officials';
        $positionOptions = OfficialProfile::officialPositionOptions();
        $presidentTerms = SkOfficialTermDates::presidentTermDates($tenantId);
        $termLockedToPresident = $presidentTerms !== null;
        $leadershipTermDefaults = $presidentTerms ?? SkOfficialTermDates::leadershipDefaults($tenantId);
        $defaultTermStart = $leadershipTermDefaults['term_start'];
        $defaultTermEnd = $leadershipTermDefaults['term_end'];
        // Relative path only — absolute route() can point at localhost APP_URL on Hostinger
        // and the browser then falls back to stale localStorage rows.
        $accountsDataUrl = Route::has('accounts.officials.data')
            ? route('accounts.officials.data', absolute: false)
            : '/accounts/officials/data';
        $accountsCacheVersion = app(SkFedPageCache::class)->version('accounts.officials');

        return view('accounts::manage_account', compact(
            'accounts',
            'accountType',
            'barangays',
            'positionOptions',
            'defaultTermStart',
            'defaultTermEnd',
            'termLockedToPresident',
            'accountsDataUrl',
            'accountsCacheVersion',
            'tenantId'
        ));
    }

    public function officialsData(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $tenantId = $this->resolveTenantId($request->user());
        $cacheVersion = app(SkFedPageCache::class)->version('accounts.officials');

        try {
            $accounts = $this->queryOfficialAccounts($tenantId);
            $data = [];

            foreach ($accounts as $account) {
                try {
                    $data[] = $this->formatAccountForTable($account);
                } catch (Throwable $e) {
                    report($e);
                    $data[] = $this->fallbackAccountRow($account);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'tenant_id' => $tenantId,
                    'count' => count($data),
                    'cache_version' => $cacheVersion,
                    'generated_at' => now()->toIso8601String(),
                ],
            ])->withHeaders($this->accountsDataNoStoreHeaders());
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load SK Officials accounts. Please refresh and try again.',
                'data' => [],
                'meta' => [
                    'tenant_id' => $tenantId,
                    'count' => 0,
                    'cache_version' => $cacheVersion,
                    'generated_at' => now()->toIso8601String(),
                ],
            ], 500)->withHeaders($this->accountsDataNoStoreHeaders());
        }
    }

    /**
     * @return array<string, string>
     */
    private function accountsDataNoStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Vary' => 'Cookie, Authorization',
        ];
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('accounts::add_sk_fed');
    }

    public function batchStore(BatchStoreAccountsRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        set_time_limit(0);

        $tenantId = $this->resolveTenantId($request->user());
        $this->ensureTenantBarangays($tenantId);

        $validated = $request->validated();
        $uploadedHeaders = $validated['headers'] ?? [];

        if (is_array($uploadedHeaders) && $uploadedHeaders !== []) {
            $missingHeaders = $this->batchTemplateService->missingRequiredHeaders($uploadedHeaders);

            if ($missingHeaders !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your Excel file is missing required columns: '.implode(', ', $missingHeaders).'.',
                    'created' => 0,
                    'failed' => [],
                    'validation_errors' => collect($missingHeaders)->map(
                        fn (string $header): array => ['row' => 0, 'error' => 'Missing required column: '.$header]
                    )->all(),
                ], 422);
            }
        }

        if ($validated['role'] === User::ROLE_SK_FED) {
            return response()->json([
                'success' => false,
                'message' => 'Federation roster members are added automatically when an SK Chairperson is created in SK Officials.',
                'created' => 0,
                'failed' => [],
                'validation_errors' => [
                    ['row' => 0, 'error' => 'Batch upload for federation accounts is disabled. Add SK Chairpersons from SK Officials instead.'],
                ],
            ], 422);
        }

        $importService = new BatchAccountImportService($tenantId);
        $validationErrors = $importService->validateRows($validated['accounts'], $validated['role']);

        if ($validationErrors !== []) {
            $previewErrors = collect($validationErrors)
                ->take(3)
                ->map(function (array $item): string {
                    $row = (int) ($item['row'] ?? 0);
                    $error = trim((string) ($item['error'] ?? 'Validation error.'));

                    return $row > 0 ? "Row {$row}: {$error}" : $error;
                })
                ->implode(' | ');

            return response()->json([
                'success' => false,
                'message' => $previewErrors !== ''
                    ? 'Please fix the validation errors in your Excel file before importing. '.$previewErrors
                    : 'Please fix the validation errors in your Excel file before importing.',
                'created' => 0,
                'failed' => [],
                'validation_errors' => $validationErrors,
            ], 422);
        }

        $result = $this->accountService->batchCreateAccounts(
            $validated['accounts'],
            $validated['role'],
            $request->user()
        );

        $created = $result['created'];
        $failedCount = count($result['failed']);
        $emailFailedCount = count($result['email_failed'] ?? []);
        $emailsSent = (int) ($result['emails_sent'] ?? 0);
        $emailsQueued = (int) ($result['emails_queued'] ?? 0);
        $total = $created + $failedCount;
        $validationErrors = array_merge(
            $result['validation_errors'] ?? [],
            collect($result['email_failed'] ?? [])->map(
                fn (array $item): array => ['row' => $item['row'], 'error' => $item['message']]
            )->all()
        );

        if ($created === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No accounts were created. Please review the uploaded rows and try again.',
                'created' => 0,
                'failed' => $result['failed'],
                'email_failed' => $result['email_failed'] ?? [],
                'validation_errors' => $validationErrors,
            ], 422);
        }

        $message = $failedCount > 0
            ? "{$created} of {$total} accounts created successfully. {$failedCount} row(s) failed."
            : "{$created} account".($created === 1 ? '' : 's').' created successfully.';

        if ($emailFailedCount > 0) {
            $message .= " {$emailsSent} password setup email(s) sent. {$emailFailedCount} email(s) could not be sent.";
        } elseif ($emailsQueued > 0) {
            $message .= " Password setup emails are being sent to {$emailsQueued} account".($emailsQueued === 1 ? '' : 's').'.';
        } else {
            $message .= ' Password setup emails were sent.';
        }

        $this->bumpAccountsCache();

        $createdAccounts = [];
        $createdUserIds = array_values(array_filter(array_map('intval', $result['created_user_ids'] ?? [])));
        if ($createdUserIds !== []) {
            $createdAccounts = User::query()
                ->with(['officialProfile.latestTerm', 'barangay'])
                ->whereIn('id', $createdUserIds)
                ->get()
                ->map(fn (User $account): array => $this->formatAccountForTable($account))
                ->values()
                ->all();
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'created' => $created,
            'emails_sent' => $emailsSent,
            'emails_queued' => $emailsQueued,
            'failed' => $result['failed'],
            'email_failed' => $result['email_failed'] ?? [],
            'validation_errors' => $validationErrors,
            'accounts' => $createdAccounts,
        ]);
    }

    public function bulkDeactivate(BulkDeactivateAccountsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $result = $this->accountService->bulkDeactivate(
            $request->validated('account_ids'),
            $request->user()
        );

        $this->bumpAccountsCache();

        $deleted = $result['deleted'];
        $failedCount = count($result['failed']);

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No accounts were deleted.',
                'deleted' => 0,
                'failed' => $result['failed'],
            ], 422);
        }

        $message = $failedCount > 0
            ? "{$deleted} SK Official(s) moved to Deleted SK Officials. {$failedCount} could not be deleted."
            : ($deleted === 1
                ? 'SK Official moved to Deleted SK Officials.'
                : "{$deleted} SK Officials moved to Deleted SK Officials.");

        return response()->json([
            'success' => true,
            'message' => $message,
            'deleted' => $deleted,
            'failed' => $result['failed'],
        ]);
    }

    public function downloadBatchTemplate(Request $request, string $type): Response
    {
        $this->authorize('create', User::class);

        $role = $type === 'federation' ? User::ROLE_SK_FED : User::ROLE_SK_OFFICIAL;

        if ($request->query('format') === 'csv') {
            return $this->batchTemplateService->downloadResponse($role);
        }

        return $this->batchTemplateService->downloadXlsxResponse($role);
    }

    public function store(StoreAccountRequest $request): Response|RedirectResponse
    {
        $this->authorize('create', User::class);

        try {
            $validatedData = $request->validated();

            if (($validatedData['role'] ?? '') === User::ROLE_SK_FED) {
                throw ValidationException::withMessages([
                    'role' => 'Federation roster members are added automatically when an SK Chairperson is created in SK Officials.',
                ]);
            }

            $tenantId = $this->resolveTenantId($request->user());
            $this->ensureTenantBarangays($tenantId);

            $user = $this->accountService->createAccount(
                $validatedData,
                $request->user()
            );
            $this->bumpAccountsCache();

            $statusMessage = empty($validatedData['password'])
                ? AccountSetupService::CREATE_SENT_MESSAGE
                : 'Account created successfully.';

            if ($request->expectsJson()) {
                $user->loadMissing(['barangay', 'officialProfile.latestTerm']);

                return response([
                    'success' => true,
                    'message' => $statusMessage,
                    'data' => $this->formatAccountForTable($user),
                ]);
            }

            $redirectRoute = $user->role === User::ROLE_SK_OFFICIAL
                ? 'accounts.officials.index'
                : 'accounts.federation.index';

            return redirect()->route($redirectRoute)
                ->with('status', $statusMessage);
        } catch (ValidationException $e) {
            Log::error('AdminAccountController@store: Validation failed.', [
                'errors' => $e->errors(),
            ]);
            throw $e;
        } catch (QueryException $e) {
            Log::error('AdminAccountController@store: Database error creating account.', [
                'message' => $e->getMessage(),
            ]);

            $message = 'Failed to create account. Please try again.';

            if (str_contains($e->getMessage(), 'official_profiles_position_check')) {
                $message = 'The selected position is not allowed. Please choose a valid SK Federation position.';
            } elseif (str_contains($e->getMessage(), 'users_email_unique')) {
                $message = 'This email is already taken.';
            }

            if ($request->expectsJson()) {
                return response([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        } catch (\Exception $e) {
            Log::error('AdminAccountController@store: Error creating account.', [
                'message' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response([
                    'success' => false,
                    'message' => 'Failed to create account. Please try again.',
                ], 500);
            }

            return back()->with('error', 'Failed to create account. Please try again.');
        }
    }

    public function update(UpdateAccountRequest $request, User $user): RedirectResponse|JsonResponse
    {
        $this->resolveTenantId($request->user());

        $this->authorize('update', $user);

        $result = $this->accountService->updateAccount($user, $request->validated(), $request->user());
        $account = $result['account'];
        $this->bumpAccountsCache();

        if ($result['email_changed']) {
            $message = $result['setup_email_sent']
                ? AccountSetupService::EMAIL_CHANGED_SENT_MESSAGE
                : ($result['setup_email_error'] ?: AccountSetupService::EMAIL_CHANGED_FAILED_MESSAGE);
        } else {
            $message = 'Account updated successfully.';
        }

        if ($request->expectsJson()) {
            $account->loadMissing(['barangay', 'officialProfile.latestTerm', 'officialProfile.terms']);

            $payload = [
                'success' => true,
                'message' => $message,
                'email_changed' => $result['email_changed'],
                'setup_email_sent' => $result['setup_email_sent'],
                'needs_setup' => app(AccountSetupService::class)->needsAccountSetup($account),
                'data' => $this->formatAccountForTable($account),
                'official_terms_synced' => (int) ($result['official_terms_synced'] ?? 0),
            ];

            if (! empty($result['leadership_terms']['term_start']) && ! empty($result['leadership_terms']['term_end'])) {
                $payload['leadership_terms'] = $result['leadership_terms'];
            }

            return response()->json($payload);
        }

        return back()->with('status', $message);
    }

    public function resendSetupEmail(Request $request, User $user): JsonResponse
    {
        $this->resolveTenantId($request->user());
        $this->authorize('resendSetupEmail', $user);

        try {
            $this->accountService->resendAccountSetupEmail($user, $request->user());
            $this->bumpAccountsCache();

            return response()->json([
                'success' => true,
                'message' => AccountSetupService::EMAIL_SENT_MESSAGE,
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: AccountSetupService::EMAIL_FAILED_MESSAGE;

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Account setup email sending failed', [
                'operation' => 'resend_setup_email_controller',
                'user_id' => $user->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => AccountSetupService::EMAIL_FAILED_MESSAGE,
            ], 500);
        }
    }

    public function updateFederationPosition(AssignFederationPositionRequest $request, User $user): JsonResponse
    {
        $this->resolveTenantId($request->user());
        $this->authorize('update', $user);

        $account = $this->federationRosterService->assignFederationPosition(
            $user,
            $request->validated('federation_position'),
            $request->user()
        );

        $officialTermsSynced = 0;
        $leadershipTerms = null;
        $account->loadMissing('officialProfile.latestTerm');
        $federationPosition = trim((string) ($account->officialProfile?->federation_position ?? ''));
        if (strcasecmp($federationPosition, 'President') === 0) {
            $presidentTerm = $account->officialProfile?->latestTerm;
            if ($presidentTerm?->term_start && $presidentTerm?->term_end) {
                $termStart = $presidentTerm->term_start->toDateString();
                $termEnd = $presidentTerm->term_end->toDateString();
                $leadershipTerms = [
                    'term_start' => $termStart,
                    'term_end' => $termEnd,
                ];
                $officialTermsSynced = $this->accountService->syncSkOfficialTermsFromLeadership(
                    (int) $request->user()->tenant_id,
                    $termStart,
                    $termEnd,
                    (int) $account->id,
                );
            }
        }

        $this->bumpAccountsCache();

        $message = 'Federation position updated successfully.';
        if ($officialTermsSynced > 0) {
            $message = 'Federation position updated. President term dates applied to '
                .$officialTermsSynced
                .' SK Official'
                .($officialTermsSynced === 1 ? '' : 's')
                .'.';
        }

        $payload = [
            'success' => true,
            'message' => $message,
            'official_terms_synced' => $officialTermsSynced,
            'data' => [
                'id' => $account->id,
                'federation_position' => $account->officialProfile?->federation_position,
            ],
        ];

        if ($leadershipTerms !== null) {
            $payload['leadership_terms'] = $leadershipTerms;
        }

        return response()->json($payload);
    }

    public function deactivate(Request $request, int $user): RedirectResponse|JsonResponse
    {
        $admin = $request->user();
        $this->resolveTenantId($admin);

        $target = User::query()
            ->whereKey($user)
            ->where('tenant_id', $admin->tenant_id)
            ->first();

        // Soft-deleted rows are excluded from default binding — treat as already archived.
        if (! $target) {
            $trashed = User::onlyTrashed()
                ->whereKey($user)
                ->where('tenant_id', $admin->tenant_id)
                ->first();

            if ($trashed) {
                $this->bumpAccountsCache();

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'already_deleted' => true,
                        'message' => 'SK Official was already deleted and is in Deleted SK Officials.',
                        'deleted_id' => (int) $trashed->id,
                    ]);
                }

                return redirect()
                    ->route($this->accountsListRouteFor($trashed))
                    ->with('status', 'SK Official was already deleted and is in Deleted SK Officials.');
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'not_found' => true,
                    'message' => 'Failed to delete SK Official.',
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors(['account' => 'Failed to delete SK Official.']);
        }

        $this->authorize('deactivate', $target);

        $this->accountService->deactivate($target, $admin);
        $this->bumpAccountsCache();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'SK Official moved to Deleted SK Officials.',
                'deleted_id' => (int) $target->id,
            ]);
        }

        return redirect()
            ->route($this->accountsListRouteFor($target))
            ->with('status', 'SK Official moved to Deleted SK Officials.');
    }

    private function accountsListRouteFor(User $account): string
    {
        return $account->role === User::ROLE_SK_OFFICIAL
            ? 'accounts.officials.index'
            : 'accounts.federation.index';
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->resolveTenantId($request->user());

        $this->authorize('resetPassword', $user);

        $payload = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $newPassword = $payload['password'] ?? Str::random(16);
        $this->accountService->resetPassword($user, $newPassword, $request->user());

        return back()->with('status', 'Password reset successfully.');
    }

    public function extendTerm(ExtendTermRequest $request, OfficialProfile $officialProfile): RedirectResponse
    {
        $this->resolveTenantId($request->user());

        $this->authorize('extendTerm', $officialProfile);

        $this->accountService->extendTerm($officialProfile, $request->validated(), $request->user());

        return back()->with('status', 'Term extended successfully.');
    }

    private function bumpAccountsCache(): void
    {
        $pageCache = app(SkFedPageCache::class);
        $pageCache->bumpMany([
            'accounts.federation',
            'accounts.officials',
            'archive.deleted_sk_officials',
            'archive.sk_officials_records',
            'archive.sk_federation_records',
        ]);
    }

    private function scheduleExpiredTermProcessing(int $tenantId, User $actor): void
    {
        $lockKey = "sk_fed:expire_terms:schedule:{$tenantId}";

        if (! Cache::add($lockKey, 1, now()->addMinutes(5))) {
            return;
        }

        $actorId = (int) $actor->id;

        dispatch(function () use ($tenantId, $actorId): void {
            $actor = User::query()->find($actorId);
            $processed = app(ExpiredTermProcessorService::class)->processForTenant($tenantId, $actor);

            if ($processed > 0) {
                app(SkFedPageCache::class)->bumpMany([
                    'accounts.federation',
                    'accounts.officials',
                    'archive.deleted_sk_officials',
                    'archive.sk_officials_records',
                    'archive.sk_federation_records',
                ]);
            }
        })->afterResponse();
    }

    /**
     * Live SK Officials users for Manage SK Officials Account.
     * Soft-deleted accounts stay in the Deleted SK Officials archive.
     *
     * @return Collection<int, User>
     */
    private function queryOfficialAccounts(int $tenantId)
    {
        return User::query()
            ->with(['barangay', 'officialProfile.latestTerm'])
            ->where(function ($roleQuery): void {
                $roleQuery->where('role', User::ROLE_SK_OFFICIAL)
                    ->orWhereRaw('LOWER(TRIM(role)) = ?', ['sk_official']);
            })
            // Strict tenant match so Manage SK Officials mirrors the live users table for this federation.
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAccountForTable(User $account): array
    {
        $profile = $account->officialProfile;
        $term = $profile?->latestTerm;
        $firstName = $profile?->first_name ? mb_strtoupper($profile->first_name, 'UTF-8') : null;
        $lastName = $profile?->last_name ? mb_strtoupper($profile->last_name, 'UTF-8') : null;
        $middleName = trim((string) ($profile?->middle_name ?? ''));
        $middleNameDisplay = $middleName !== ''
            ? mb_strtoupper($middleName, 'UTF-8')
            : null;
        $suffixDisplay = $profile?->suffix;
        if (in_array($suffixDisplay, ['NONE', 'None', null, ''], true)) {
            $suffixDisplay = null;
        }
        $nameCore = collect([$lastName, $firstName])->filter()->implode(', ');
        $fullName = trim(collect([$nameCore, $middleNameDisplay, $suffixDisplay])->filter()->implode(' '));
        $displayName = $fullName !== '' ? $fullName : ($account->name ?? 'N/A');
        $sortName = strtolower(trim(collect([$lastName, $firstName, $middleNameDisplay, $suffixDisplay])->filter()->implode(' ')));
        $federationPosition = $profile?->displayFederationPosition();
        $filterPosition = $account->role === User::ROLE_SK_OFFICIAL
            ? ($profile?->position ?? '')
            : ($profile?->federation_position ?? '');
        $searchBlob = strtolower(trim(collect([
            $displayName,
            $account->email,
            $account->barangay?->name,
            $profile?->position,
            $federationPosition,
        ])->filter()->implode(' ')));

        $verifiedAt = $account->email_verified_at;
        $emailVerifiedDisplay = null;
        if ($verifiedAt instanceof \DateTimeInterface) {
            $emailVerifiedDisplay = $verifiedAt->format('m/d/Y h:i A');
        } elseif (is_string($verifiedAt) && $verifiedAt !== '') {
            $emailVerifiedDisplay = $verifiedAt;
        }

        $needsSetup = false;
        try {
            $needsSetup = app(AccountSetupService::class)->needsAccountSetup($account);
        } catch (Throwable $e) {
            report($e);
        }

        $isIncoming = false;
        try {
            $isIncoming = $account->isIncomingTurnoverOfficer();
        } catch (Throwable $e) {
            report($e);
        }

        return [
            'id' => $account->id,
            'role' => $account->role,
            'email' => $account->email,
            'status' => $account->status,
            'email_verified_at' => $emailVerifiedDisplay,
            'needs_setup' => $needsSetup,
            'display_name' => $displayName,
            'sort_name' => $sortName,
            'search_text' => $searchBlob,
            'first_name' => $firstName ?? '',
            'last_name' => $lastName ?? '',
            'middle_name' => $middleName !== '' ? mb_strtoupper($middleName, 'UTF-8') : '',
            'suffix' => $profile?->suffix ?? '',
            'sex' => $profile?->sex ?? '',
            'date_of_birth' => $profile?->date_of_birth?->toDateString() ?? '',
            'age' => $profile?->age,
            'contact_number' => $profile?->contact_number ?? '',
            'position' => $profile?->position ?? '',
            'federation_position' => $profile?->federation_position ?? '',
            'federation_position_label' => $federationPosition,
            'filter_position' => $filterPosition,
            'barangay_id' => $account->barangay_id,
            'barangay_name' => $account->barangay?->name ?? '',
            'municipality' => $profile?->municipality ?? '',
            'province' => $profile?->province ?? '',
            'region' => $profile?->region ?? '',
            'term_status' => $term?->status ?? 'ACTIVE',
            'term_start' => $term?->term_start?->toDateString() ?? '',
            'term_end' => $term?->term_end?->toDateString() ?? '',
            'term_start_display' => $term?->term_start?->format('m/d/Y') ?? '—',
            'term_end_display' => $term?->term_end?->format('m/d/Y') ?? '—',
            'is_incoming_turnover' => $isIncoming,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackAccountRow(User $account): array
    {
        $displayName = trim((string) ($account->name ?: $account->email ?: 'N/A'));

        return [
            'id' => $account->id,
            'role' => $account->role,
            'email' => $account->email,
            'status' => $account->status,
            'email_verified_at' => null,
            'needs_setup' => false,
            'display_name' => $displayName !== '' ? $displayName : 'N/A',
            'sort_name' => strtolower($displayName),
            'search_text' => strtolower(trim($displayName.' '.($account->email ?? ''))),
            'first_name' => '',
            'last_name' => '',
            'middle_name' => '',
            'suffix' => '',
            'sex' => '',
            'date_of_birth' => '',
            'age' => null,
            'contact_number' => '',
            'position' => '',
            'federation_position' => '',
            'federation_position_label' => null,
            'filter_position' => '',
            'barangay_id' => $account->barangay_id,
            'barangay_name' => $account->barangay?->name ?? '',
            'municipality' => '',
            'province' => '',
            'region' => '',
            'term_status' => 'ACTIVE',
            'term_start' => '',
            'term_end' => '',
            'term_start_display' => '—',
            'term_end_display' => '—',
            'is_incoming_turnover' => false,
        ];
    }

    private function resolveTenantId(User $admin): int
    {
        if ($admin->tenant_id !== null) {
            return $admin->tenant_id;
        }

        $tenant = Tenant::query()->firstOrCreate(
            ['code' => 'santa_cruz'],
            [
                'name' => 'Santa Cruz Federation',
                'municipality' => 'Santa Cruz',
                'province' => 'Laguna',
                'region' => 'IV-A CALABARZON',
                'is_active' => true,
            ]
        );

        $admin->forceFill(['tenant_id' => $tenant->id])->save();

        return $tenant->id;
    }

    private function ensureTenantBarangays(int $tenantId): void
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            return;
        }

        if (Barangay::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        BarangaySeeder::seedTenant($tenant);
    }
}
