<?php

namespace App\Modules\SK_Officials_Records\Controllers;

use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use App\Modules\SK_Officials_Records\Services\ArchivedTermRecordsBackfillService;
use App\Modules\SK_Officials_Records\Services\ExpiredTermProcessorService;
use App\Modules\SK_Officials_Records\Services\SkOfficialsRecordsService;
use App\Services\SkFedPageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SkOfficialsRecordsController extends Controller
{
    public function __construct(
        private readonly SkOfficialsRecordsService $skOfficialsRecordsService,
    ) {}

    public function index(Request $request): View
    {
        $tenantId = $this->resolveTenantId($request->user());
        $this->syncArchiveTables($tenantId, $request->user());

        $payload = $this->skOfficialsRecordsService->listRecords($tenantId, $request);

        return view('SK_Officials_Records::SK_Officials_Records', [
            'initialRecords' => $payload['data'] ?? [],
            'initialFilters' => $payload['filters'] ?? [],
            'initialStats' => $payload['stats'] ?? ['total' => 0],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->user());
        $this->syncArchiveTables($tenantId, $request->user());

        // Always return live archive rows so Records shows every completed term.
        $payload = $this->skOfficialsRecordsService->listRecords($tenantId, $request);

        return response()->json($payload)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function syncArchiveTables(int $tenantId, User $actor): void
    {
        $pageCache = app(SkFedPageCache::class);

        try {
            $processed = app(ExpiredTermProcessorService::class)
                ->processForTenant($tenantId, $actor);
            $backfill = app(ArchivedTermRecordsBackfillService::class)->backfill($tenantId);

            if ($processed > 0 || ($backfill['officials_inserted'] + $backfill['federation_inserted']) > 0) {
                $pageCache->bumpMany([
                    'archive.sk_officials_records',
                    'archive.sk_federation_records',
                    'accounts.officials',
                    'accounts.federation',
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
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
        return (int) ($user->tenant_id
            ?: Tenant::query()->where('code', config('sk_fed_auth.tenant_code'))->value('id')
            ?: 0);
    }
}
