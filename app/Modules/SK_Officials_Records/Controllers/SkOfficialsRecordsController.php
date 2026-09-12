<?php

namespace App\Modules\SK_Officials_Records\Controllers;

use App\Modules\SK_Officials_Records\Services\SkOfficialsRecordsService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use App\Services\SkFedPageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SkOfficialsRecordsController extends Controller
{
    public function __construct(
        private readonly SkOfficialsRecordsService $skOfficialsRecordsService,
    ) {
    }

    public function index(): View
    {
        return view('SK_Officials_Records::SK_Officials_Records');
    }

    public function data(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->user());
        $pageCache = app(SkFedPageCache::class);
        $parts = [
            'tenant' => $tenantId,
            'search' => (string) $request->query('search', ''),
            'year' => (string) $request->query('year', ''),
            'term' => (string) $request->query('term', ''),
            'barangay' => (string) $request->query('barangay', ''),
            'position' => (string) $request->query('position', ''),
        ];

        return response()->json(
            $pageCache->remember('archive.sk_officials_records', function () use ($tenantId, $request) {
                return $this->skOfficialsRecordsService->listRecords($tenantId, $request);
            }, $parts)
        )->header('Cache-Control', 'private, max-age=60');
    }

    public function restore(Request $request, int $record): JsonResponse
    {
        $user = $this->skOfficialsRecordsService->restoreRecord($record, $request->user());

        app(SkFedPageCache::class)->bumpMany([
            'archive.sk_officials_records',
            'accounts.officials',
            'archive.deleted_sk_officials',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SK Official account restored successfully.',
            'user_id' => $user->id,
        ]);
    }

    private function resolveTenantId(User $user): int
    {
        if ($user->tenant_id) {
            return (int) $user->tenant_id;
        }

        return (int) Tenant::query()->where('code', 'santa_cruz')->value('id');
    }
}
