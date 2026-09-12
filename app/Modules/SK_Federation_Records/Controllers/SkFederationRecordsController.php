<?php

namespace App\Modules\SK_Federation_Records\Controllers;

use App\Modules\SK_Federation_Records\Services\SkFederationRecordsService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use App\Services\SkFedPageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SkFederationRecordsController extends Controller
{
    public function __construct(
        private readonly SkFederationRecordsService $skFederationRecordsService,
    ) {
    }

    public function index(): View
    {
        return view('SK_Federation_Records::SK_Federation_Records');
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
            'position' => (string) $request->query('position', ''),
        ];

        return response()->json(
            $pageCache->remember('archive.sk_federation_records', function () use ($tenantId, $request) {
                return $this->skFederationRecordsService->listRecords($tenantId, $request);
            }, $parts)
        )->header('Cache-Control', 'private, max-age=60');
    }

    public function restore(Request $request, int $record): JsonResponse
    {
        $user = $this->skFederationRecordsService->restoreRecord($record, $request->user());

        app(SkFedPageCache::class)->bumpMany([
            'archive.sk_federation_records',
            'accounts.officials',
            'archive.deleted_sk_officials',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SK Federation account restored successfully.',
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
