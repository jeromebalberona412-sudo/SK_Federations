<?php

namespace App\Modules\Archive_Management\Controllers;

use App\Modules\Archive_Management\Services\ArchivedRecordRestoreService;
use App\Modules\Archive_Management\Services\TermRecordsArchiveService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermRecordsArchiveController extends Controller
{
    public function __construct(
        private readonly TermRecordsArchiveService $termRecordsArchiveService,
        private readonly ArchivedRecordRestoreService $archivedRecordRestoreService,
    ) {
    }

    public function officialsData(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->user());
        $pageCache = app(\App\Services\SkFedPageCache::class);
        $parts = [
            'tenant' => $tenantId,
            'search' => (string) $request->query('search', ''),
            'year' => (string) $request->query('year', ''),
            'term' => (string) $request->query('term', ''),
        ];

        return response()->json(
            $pageCache->remember('archive.sk_officials_records', function () use ($tenantId, $request) {
                return $this->termRecordsArchiveService->listOfficialRecords($tenantId, $request);
            }, $parts)
        )->header('Cache-Control', 'private, max-age=60');
    }

    public function federationData(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->user());
        $pageCache = app(\App\Services\SkFedPageCache::class);
        $parts = [
            'tenant' => $tenantId,
            'search' => (string) $request->query('search', ''),
            'year' => (string) $request->query('year', ''),
            'term' => (string) $request->query('term', ''),
        ];

        return response()->json(
            $pageCache->remember('archive.sk_federation_records', function () use ($tenantId, $request) {
                return $this->termRecordsArchiveService->listFederationRecords($tenantId, $request);
            }, $parts)
        )->header('Cache-Control', 'private, max-age=60');
    }

    public function restoreOfficialRecord(Request $request, int $record): JsonResponse
    {
        $user = $this->archivedRecordRestoreService->restoreOfficialRecord($record, $request->user());

        app(\App\Services\SkFedPageCache::class)->bumpMany([
            'archive.sk_officials_records',
            'accounts.officials',
            'archive.deleted_sk_officials',
        ]);

        return response()->json([
            'message' => 'SK Official account restored successfully.',
            'user_id' => $user->id,
        ]);
    }

    public function restoreFederationRecord(Request $request, int $record): JsonResponse
    {
        $user = $this->archivedRecordRestoreService->restoreFederationRecord($record, $request->user());

        app(\App\Services\SkFedPageCache::class)->bumpMany([
            'archive.sk_federation_records',
            'accounts.federation',
            'archive.deleted_sk_officials',
        ]);

        return response()->json([
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
