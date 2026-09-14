<?php

namespace App\Modules\Deleted_SK_Officials\Controllers;

use App\Modules\Deleted_SK_Officials\Services\DeletedSkOfficialsService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use App\Services\SkFedPageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DeletedSkOfficialsController extends Controller
{
    public function __construct(private readonly DeletedSkOfficialsService $deletedArchiveService) {}

    public function officialsIndex(Request $request): View
    {
        $tenantId = $this->resolveTenantId($request->user());
        $this->purgeExpired($tenantId);

        $result = $this->deletedArchiveService->listDeletedAccounts(
            User::ROLE_SK_OFFICIAL,
            $tenantId,
            $request
        );

        $deletedOfficials = collect($result['data'] ?? [])
            ->map(fn (array $item) => new Fluent($item));

        $filters = $result['filters'] ?? $this->deletedArchiveService->filterCatalog($tenantId, User::ROLE_SK_OFFICIAL);
        $stats = $result['stats'] ?? [
            'total' => $deletedOfficials->count(),
            'today' => 0,
            'month' => 0,
        ];

        return view('Deleted_SK_Officials::deleted-sk-officials', [
            'deletedOfficials' => $deletedOfficials,
            'stats' => $stats,
            'filterBarangays' => $filters['barangays'] ?? [],
            'filterPositions' => $filters['positions'] ?? [],
            'filterYears' => $filters['years'] ?? [],
            'currentFilter' => (string) $request->query('filter', 'all'),
            'currentYear' => (string) $request->query('year', 'all'),
            'currentBarangay' => (string) $request->query('barangay', ''),
            'currentPosition' => (string) $request->query('position', ''),
            'currentSearch' => (string) $request->query('search', ''),
            'retentionDays' => DeletedSkOfficialsService::RETENTION_DAYS,
        ]);
    }

    public function officialsData(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->user());
        $this->purgeExpired($tenantId);

        $pageCache = app(SkFedPageCache::class);
        $parts = [
            'tenant' => $tenantId,
            'filter' => (string) $request->query('filter', ''),
            'year' => (string) $request->query('year', ''),
            'search' => (string) $request->query('search', ''),
            'barangay' => (string) $request->query('barangay', ''),
            'position' => (string) $request->query('position', ''),
            'term' => (string) $request->query('term', ''),
        ];

        $payload = $pageCache->remember('archive.deleted_sk_officials', function () use ($tenantId, $request) {
            return $this->deletedArchiveService->listDeletedAccounts(
                User::ROLE_SK_OFFICIAL,
                $tenantId,
                $request
            );
        }, $parts);

        return response()->json($payload)->header('Cache-Control', 'no-store, private');
    }

    public function showOfficial(Request $request, int $account): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->user());

        $record = $this->deletedArchiveService->getDeletedAccount(
            $account,
            $tenantId,
            User::ROLE_SK_OFFICIAL
        );

        if ($record === null) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found in Deleted SK Officials archive.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function restoreOfficial(Request $request, int $account): JsonResponse
    {
        $admin = $request->user();
        $this->resolveTenantId($admin);

        try {
            $user = $this->deletedArchiveService->restoreAccount(
                $account,
                User::ROLE_SK_OFFICIAL,
                $admin
            );
        } catch (ValidationException $exception) {
            $message = (string) (collect($exception->errors())->flatten()->first()
                ?? 'Unable to restore SK Official account.');

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $exception->errors(),
            ], 422);
        }

        app(SkFedPageCache::class)->bumpMany([
            'archive.deleted_sk_officials',
            'accounts.officials',
            'archive.sk_officials_records',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SK Official restored successfully.',
            'data' => [
                'id' => $user->id,
                'full_name' => $user->name,
            ],
        ]);
    }

    /**
     * Enforce the 30-day window on page load so retention holds even without the scheduler.
     */
    private function purgeExpired(int $tenantId): void
    {
        if ($this->deletedArchiveService->purgeExpiredDeletedOfficials($tenantId) > 0) {
            app(SkFedPageCache::class)->bumpMany([
                'archive.deleted_sk_officials',
                'accounts.officials',
                'archive.sk_officials_records',
            ]);
        }
    }

    private function resolveTenantId(User $admin): int
    {
        if ($admin->tenant_id !== null) {
            return (int) $admin->tenant_id;
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

        return (int) $tenant->id;
    }
}
