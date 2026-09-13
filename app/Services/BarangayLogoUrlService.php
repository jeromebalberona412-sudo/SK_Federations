<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $map = $this->resolveMany([$barangayId]);

        return $map[$barangayId] ?? null;
    }

    /**
     * @param  list<int>  $barangayIds
     * @return array<int, string|null>
     */
    public function resolveMany(array $barangayIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $barangayIds))));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = null;
        }

        if ($ids === [] || ! Schema::hasTable('barangay_logos')) {
            return $out;
        }

        $rows = DB::table('barangay_logos')
            ->whereIn('barangay_id', $ids)
            ->orderByDesc('updated_at')
            ->get(['barangay_id', 'url', 'cloudinary_public_id', 'cloudinary_version', 'updated_at']);

        $seen = [];
        foreach ($rows as $logo) {
            $bid = (int) $logo->barangay_id;
            if (isset($seen[$bid])) {
                continue;
            }
            $seen[$bid] = true;

            $url = $logo->url;
            if ($logo->cloudinary_public_id && $this->cloudinary->isConfigured()) {
                $version = $logo->cloudinary_version
                    ? (int) $logo->cloudinary_version
                    : $this->cloudinary->extractVersionFromUrl((string) $logo->url);
                $url = $this->cloudinary->deliverUrl($logo->cloudinary_public_id, $version);
            }
            $out[$bid] = CloudinaryService::cacheBust((string) $url, $logo->updated_at);
        }

        return $out;
    }
}
