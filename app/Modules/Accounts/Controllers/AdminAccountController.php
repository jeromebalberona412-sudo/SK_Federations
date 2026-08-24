<?php

namespace App\Modules\Accounts\Controllers;

use App\Modules\Accounts\Database\Seeders\BarangaySeeder;
use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Archive_Management\Services\ExpiredTermProcessorService;
use App\Modules\Accounts\Requests\AssignFederationPositionRequest;
use App\Modules\Accounts\Requests\BatchStoreAccountsRequest;
use App\Modules\Accounts\Requests\ExtendTermRequest;
use App\Modules\Accounts\Requests\StoreAccountRequest;
use App\Modules\Accounts\Requests\UpdateAccountRequest;
use App\Modules\Accounts\Requests\BulkDeactivateAccountsRequest;
use App\Modules\Accounts\Services\AccountBatchTemplateService;
use App\Modules\Accounts\Services\AccountService;
use App\Modules\Accounts\Services\FederationRosterService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Authentication\Services\BootstrapSkFedAdminService;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class AdminAccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly ExpiredTermProcessorService $expiredTermProcessor,
        private readonly AccountBatchTemplateService $batchTemplateService,
        private readonly FederationRosterService $federationRosterService,
    ) {
    }

    public function indexFederation(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $tenantId = $this->resolveTenantId($request->user());
        $this->ensureTenantBarangays($tenantId);
        $this->expiredTermProcessor->processForTenant($tenantId, $request->user());
        $this->federationRosterService->syncFederationRosterAccess($tenantId);

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

        return view('accounts::manage_account', compact('accounts', 'accountType', 'barangays', 'positionOptions', 'takenFederationPositions'));
    }

    public function indexOfficials(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $tenantId = $this->resolveTenantId($request->user());
        $this->ensureTenantBarangays($tenantId);
        $this->expiredTermProcessor->processForTenant($tenantId, $request->user());

        $query = User::query()
            ->with(['barangay', 'officialProfile.latestTerm'])
            ->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->whereHas('officialProfile.terms', function ($termQuery) {
                $today = now()->startOfDay();

                $termQuery->where(function ($scope) use ($today) {
                    $scope->where(function ($active) use ($today) {
                        $active
                            ->where('status', OfficialTerm::STATUS_ACTIVE)
                            ->whereDate('term_end', '>=', $today);
                    })->orWhere(function ($upcoming) use ($today) {
                        $upcoming
                            ->where('status', OfficialTerm::STATUS_INACTIVE)
                            ->whereDate('term_start', '>=', $today);
                    });
                });
            })
            ->orderByDesc('created_at');

        $accounts = $query->get();
        $barangays = Barangay::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $accountType = 'sk_officials';
        $positionOptions = OfficialProfile::officialPositionOptions();

        return view('accounts::manage_account', compact('accounts', 'accountType', 'barangays', 'positionOptions'));
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

        $importService = new \App\Modules\Accounts\Services\BatchAccountImportService($tenantId);
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
        } else {
            $message .= ' Password setup emails were sent.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'created' => $created,
            'emails_sent' => $emailsSent,
            'failed' => $result['failed'],
            'email_failed' => $result['email_failed'] ?? [],
            'validation_errors' => $validationErrors,
        ]);
    }

    public function bulkDeactivate(BulkDeactivateAccountsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $result = $this->accountService->bulkDeactivate(
            $request->validated('account_ids'),
            $request->user()
        );

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
            ? "{$deleted} account(s) deleted. {$failedCount} could not be deleted."
            : "{$deleted} account(s) deleted successfully.";

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

        Log::info('AdminAccountController@store: Request received.');
        try {
            $validatedData = $request->validated();
            Log::info('AdminAccountController@store: Validation passed.', $validatedData);

            if (($validatedData['role'] ?? '') === User::ROLE_SK_FED) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'role' => 'Federation roster members are added automatically when an SK Chairperson is created in SK Officials.',
                ]);
            }

            $tenantId = $this->resolveTenantId($request->user());
            $this->ensureTenantBarangays($tenantId);

            $user = $this->accountService->createAccount(
                $validatedData,
                $request->user()
            );
            Log::info('AdminAccountController@store: Account created successfully.', ['user_id' => $user->id]);

            $statusMessage = empty($validatedData['password'])
                ? 'Account created successfully. A password setup email has been sent.'
                : 'Account created successfully.';

            if ($request->expectsJson()) {
                return response([
                    'success' => true,
                    'message' => $statusMessage,
                    'data' => ['id' => $user->id],
                ]);
            }

            $redirectRoute = $user->role === User::ROLE_SK_OFFICIAL
                ? 'accounts.officials.index'
                : 'accounts.federation.index';

            return redirect()->route($redirectRoute)
                ->with('status', $statusMessage);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('AdminAccountController@store: Validation failed.', [
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);
            throw $e;
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('AdminAccountController@store: Database error creating account.', [
                'message' => $e->getMessage(),
                'input' => $request->all(),
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
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
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

        $this->accountService->updateAccount($user, $request->validated(), $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully.',
            ]);
        }

        return back()->with('status', 'Account updated successfully.');
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

        return response()->json([
            'success' => true,
            'message' => 'Federation position updated successfully.',
            'data' => [
                'id' => $account->id,
                'federation_position' => $account->officialProfile?->federation_position,
            ],
        ]);
    }

    public function deactivate(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->resolveTenantId($request->user());

        $this->authorize('deactivate', $user);

        $this->accountService->deactivate($user, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Account deleted and moved to archive.',
            ]);
        }

        return back()->with('status', 'Account deactivated successfully.');
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
