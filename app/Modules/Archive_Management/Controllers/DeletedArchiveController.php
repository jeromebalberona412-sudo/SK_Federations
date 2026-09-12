<?php

namespace App\Modules\Archive_Management\Controllers;

use App\Modules\Archive_Management\Services\DeletedArchiveService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use App\Services\SkFedPageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeletedArchiveController extends Controller
{
    public function __construct(private readonly DeletedArchiveService $deletedArchiveService) {}

    public function officialsIndex(Request $request): View
    {
        $tenantId = $this->resolveTenantId($request->user());
        $filters = $this->deletedArchiveService->filterCatalog($tenantId, User::ROLE_SK_OFFICIAL);

        return view('archive-management::deleted-sk-officials', [
            'filterBarangays' => $filters['barangays'] ?? [],
            'filterPositions' => $filters['positions'] ?? [],
            'filterTerms' => $filters['terms'] ?? [],
            'filterYears' => $filters['years'] ?? [],
        ]);
    }

    public function officialsData(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->user());
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

        return response()->json($payload)->header('Cache-Control', 'private, max-age=60');
    }

    public function restoreOfficial(Request $request, int $account): JsonResponse
    {
        $admin = $request->user();
        $this->resolveTenantId($admin);

        $user = $this->deletedArchiveService->restoreAccount(
            $account,
            User::ROLE_SK_OFFICIAL,
            $admin
        );

        app(SkFedPageCache::class)->bumpMany([
            'archive.deleted_sk_officials',
            'accounts.officials',
            'archive.sk_officials_records',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account restored to the SK Officials list.',
            'data' => [
                'id' => $user->id,
                'full_name' => $user->name,
            ],
        ]);
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
}
