<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BarangayLogoUrlService
{
    public function __construct(private readonly CloudinaryService $cloudinary)
    {
    }

    public function resolve(?int $barangayId): ?string
    {
        if (! $barangayId) {
            return null;
        }

        $logo = DB::table('barangay_logos')
            ->where('barangay_id', $barangayId)
            ->orderByDesc('updated_at')
            ->first(['url', 'cloudinary_public_id', 'cloudinary_version', 'updated_at']);

        if (! $logo) {
            return null;
        }

        $url = $logo->url;

        if ($logo->cloudinary_public_id && $this->cloudinary->isConfigured()) {
            $version = $logo->cloudinary_version
                ? (int) $logo->cloudinary_version
                : $this->cloudinary->extractVersionFromUrl((string) $logo->url);

            $url = $this->cloudinary->deliverUrl($logo->cloudinary_public_id, $version);
        }

        return CloudinaryService::cacheBust((string) $url, $logo->updated_at);
    }
}
