<?php

namespace App\Modules\BarangayLogos\Controllers;

use App\Modules\Authentication\Services\AuthAuditLogService;
use App\Modules\Authentication\Services\TenantContextService;
use App\Modules\BarangayLogos\Models\BarangayLogo;
use App\Modules\BarangayLogos\Requests\BarangayLogoRequest;
use App\Modules\BarangayLogos\Services\CloudinaryService;
use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Controllers\Controller;
use App\Services\BarangayLogoUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class BarangayLogoController extends Controller
{
    public function __construct(
        private readonly CloudinaryService $cloudinary,
        private readonly BarangayLogoUrlService $logoUrls,
        private readonly TenantContextService $tenantContext,
        private readonly AuthAuditLogService $auditLog,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tenantId = $this->resolveTenantId();

        $barangays = Barangay::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $logos = BarangayLogo::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('barangay_id');

        foreach ($logos as $logo) {
            $logo->url = $this->logoUrls->resolve($logo->barangay_id) ?? $logo->url;
        }

        return view('barangay_logos::barangay-logos', [
            'user'         => $user,
            'barangays'    => $barangays,
            'logos'        => $logos,
            'logosVisible' => true,
        ]);
    }

    public function upload(BarangayLogoRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->resolveTenantId();
        $barangayId = (int) $request->input('barangay_id');

        $barangay = Barangay::query()
            ->where('id', $barangayId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $existing = BarangayLogo::query()
            ->where('barangay_id', $barangayId)
            ->where('tenant_id', $tenantId)
            ->first();

        try {
            if ($existing) {
                $this->cloudinary->delete($existing->cloudinary_public_id);
            }

            $publicId = 'barangay_'.$barangayId.'_tenant_'.$tenantId.'_'.Str::lower(Str::random(8));
            $result = $this->cloudinary->upload($request->file('logo'), $publicId, true);

            $logo = BarangayLogo::updateOrCreate(
                ['barangay_id' => $barangayId, 'tenant_id' => $tenantId],
                [
                    'uploaded_by'          => $user->id,
                    'cloudinary_public_id' => $result['public_id'],
                    'cloudinary_version'   => $result['version'],
                    'url'                  => $result['url'],
                ]
            );

            $this->auditLog->log(
                event: 'barangay_logos.'.($existing ? 'update' : 'upload'),
                user: $user,
                request: $request,
                metadata: [
                    'action' => $existing ? 'update_barangay_logo' : 'upload_barangay_logo',
                    'module' => 'barangay_logos',
                    'barangay_id' => $barangayId,
                    'barangay_name' => $barangay->name,
                ],
                resourceType: 'barangay_logo',
                resourceId: (string) $logo->id,
            );

            return response()->json([
                'id'  => $logo->id,
                'url' => $this->logoUrls->resolve($barangayId),
            ]);
        } catch (Throwable $e) {
            Log::error('Cloudinary upload failed', [
                'barangay_id' => $barangayId,
                'error'       => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Upload failed. Please try again.'], 500);
        }
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId();

        $logo = BarangayLogo::query()
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            $this->cloudinary->delete($logo->cloudinary_public_id);
            $logo->delete();

            return response()->json(['message' => 'Logo removed.']);
        } catch (Throwable $e) {
            Log::error('Cloudinary delete failed', [
                'logo_id' => $id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Delete failed. Please try again.'], 500);
        }
    }

    private function resolveTenantId(): int
    {
        $tenantId = $this->tenantContext->tenantId();

        if ($tenantId === null) {
            throw new RuntimeException('Tenant context is not configured for SK Federation.');
        }

        return $tenantId;
    }
}
