<?php

namespace App\Modules\Archive_Management\Controllers;

use App\Modules\Archive_Management\Services\DeletedArchiveService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeletedArchiveController extends Controller
{
    public function __construct(private readonly DeletedArchiveService $deletedArchiveService)
    {
    }

    public function officialsIndex(): View
    {
        return view('archive-management::deleted-sk-officials');
    }

    public function officialsData(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request->user());
        $payload = $this->deletedArchiveService->listDeletedAccounts(
            User::ROLE_SK_OFFICIAL,
            $tenantId,
            $request
        );

        return response()->json($payload);
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
