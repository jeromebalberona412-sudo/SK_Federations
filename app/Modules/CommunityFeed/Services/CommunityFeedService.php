<?php

namespace App\Modules\CommunityFeed\Services;

use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\BarangayMonitoring\Services\BarangayMonitoringService;
use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Models\Announcement;
use App\Modules\Shared\Models\User;
use App\Services\BarangayLogoUrlService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CommunityFeedService
{
    private const DEFAULT_PROFILE_COLOR = '#213F99';

    public function __construct(
        private readonly BarangayMonitoringService $monitoringService,
        private readonly BarangayLogoUrlService $logoUrls,
    ) {}

    /**
     * @return list<array{slug: string, name: string, id: int|null, logo_url: string|null, initials: string, color: string}>
     */
    public function listBarangayProfiles(?int $tenantId = null): array
    {
        $canonical = $this->monitoringService->slugToNameMap();
        $profiles = [];

        foreach ($canonical as $slug => $name) {
            $profiles[$slug] = [
                'slug' => $slug,
                'name' => $name,
                'id' => null,
                'logo_url' => null,
                'initials' => $this->initials($name),
                'color' => self::DEFAULT_PROFILE_COLOR,
            ];
        }

        if (Schema::hasTable('barangays')) {
            $query = Barangay::query()->orderBy('name');
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            foreach ($query->get() as $barangay) {
                $matchedSlug = $this->matchCanonicalSlug($canonical, (string) $barangay->name);
                if ($matchedSlug === null) {
                    continue;
                }

                $name = $canonical[$matchedSlug];
                $profiles[$matchedSlug] = [
                    'slug' => $matchedSlug,
                    'name' => $name,
                    'id' => $barangay->id,
                    'logo_url' => $this->logoUrls->resolve($barangay->id),
                    'initials' => $this->initials($name),
                    'color' => self::DEFAULT_PROFILE_COLOR,
                ];
            }
        }

        return collect($profiles)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $canonical
     */
    private function matchCanonicalSlug(array $canonical, string $name): ?string
    {
        $needle = trim($name);
        if ($needle === '') {
            return null;
        }

        foreach ($canonical as $slug => $canonicalName) {
            if (strcasecmp($canonicalName, $needle) === 0) {
                return $slug;
            }

            if (Str::slug($needle) === $slug || Str::slug($needle) === Str::slug($canonicalName)) {
                return $slug;
            }
        }

        return null;
    }

    public function resolveBarangayProfile(string $slug, ?int $tenantId = null): ?array
    {
        $profiles = $this->listBarangayProfiles($tenantId);

        foreach ($profiles as $profile) {
            if ($profile['slug'] === $slug) {
                return $profile;
            }
        }

        $mappedName = $this->monitoringService->nameFromSlug($slug);
        if ($mappedName === null) {
            return null;
        }

        foreach ($profiles as $profile) {
            if (strcasecmp((string) $profile['name'], $mappedName) === 0) {
                return $profile;
            }
        }

        return [
            'slug' => $slug,
            'name' => $mappedName,
            'id' => null,
            'logo_url' => null,
            'initials' => $this->initials($mappedName),
            'color' => self::DEFAULT_PROFILE_COLOR,
        ];
    }

    /**
     * @return list<array{name: string, role: string, initials: string, logo_url: string|null}>
     */
    public function listOfficialsForBarangay(?int $barangayId, ?string $logoUrl = null): array
    {
        if ($barangayId === null || ! Schema::hasTable('users')) {
            return [];
        }

        return User::query()
            ->with('officialProfile')
            ->where('barangay_id', $barangayId)
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->where('status', User::STATUS_ACTIVE)
            ->get()
            ->map(function (User $official) use ($logoUrl) {
                $name = trim((string) $official->name);
                $role = trim((string) ($official->officialProfile?->position ?? 'SK Official'));

                return [
                    'name' => $name !== '' ? $name : 'SK Official',
                    'role' => $role !== '' ? $role : 'SK Official',
                    'initials' => $this->initials($name !== '' ? $name : 'SK'),
                    'logo_url' => $logoUrl,
                    'sort_key' => $this->positionSortKey($role),
                ];
            })
            ->sortBy([
                ['sort_key', 'asc'],
                ['name', 'asc'],
            ])
            ->map(fn (array $official) => [
                'name' => $official['name'],
                'role' => $official['role'],
                'initials' => $official['initials'],
                'logo_url' => $official['logo_url'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{label: string, start: string, end: string}
     */
    public function resolveBarangayTerm(?int $barangayId): array
    {
        if ($barangayId === null || ! Schema::hasTable('official_terms')) {
            return ['label' => '—', 'start' => 'N/A', 'end' => 'N/A'];
        }

        $terms = OfficialTerm::query()
            ->where('status', OfficialTerm::STATUS_ACTIVE)
            ->whereHas('officialProfile.user', function ($query) use ($barangayId) {
                $query->where('barangay_id', $barangayId)
                    ->where('role', User::ROLE_SK_OFFICIAL)
                    ->where('status', User::STATUS_ACTIVE);
            })
            ->get(['term_start', 'term_end']);

        if ($terms->isEmpty()) {
            return ['label' => '—', 'start' => 'N/A', 'end' => 'N/A'];
        }

        $start = $terms->min('term_start');
        $end = $terms->max('term_end');

        if ($start === null || $end === null) {
            return ['label' => '—', 'start' => 'N/A', 'end' => 'N/A'];
        }

        return [
            'label' => $start->format('Y').'–'.$end->format('Y'),
            'start' => $start->format('M d, Y'),
            'end' => $end->format('M d, Y'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPostsForBarangay(?int $barangayId): array
    {
        if ($barangayId === null) {
            return [];
        }

        return Announcement::query()
            ->with(['user', 'barangay', 'images'])
            ->withCount(['reactions', 'comments'])
            ->active()
            ->where('barangay_id', $barangayId)
            ->whereRaw('"is_federation_wide" = false')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Announcement $post) {
                $type = strtolower((string) $post->type);

                return [
                    'id' => $post->id,
                    'type' => ucfirst($type),
                    'type_class' => in_array($type, ['event', 'announcement', 'activity', 'program', 'update'], true)
                        ? $type
                        : 'update',
                    'author' => $post->user?->name ?: ('SK Brgy. '.($post->barangay?->name ?? '')),
                    'posted_at' => $post->created_at?->diffForHumans() ?? '',
                    'title' => $post->title ?: 'Barangay Update',
                    'text' => $post->body,
                    'image_url' => $post->images->first()?->image_url,
                    'likes' => (int) ($post->reactions_count ?? 0),
                    'comments' => (int) ($post->comments_count ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr($parts[1], 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    private function positionSortKey(string $role): int
    {
        $normalized = strtolower($role);

        return match (true) {
            str_contains($normalized, 'vice') => 1,
            str_contains($normalized, 'chair') => 0,
            str_contains($normalized, 'secretary') => 2,
            str_contains($normalized, 'treasurer') => 3,
            str_contains($normalized, 'auditor') => 4,
            str_contains($normalized, 'pro') || str_contains($normalized, 'public relations') => 5,
            str_contains($normalized, 'councilor') || str_contains($normalized, 'kagawad') => 6,
            default => 7,
        };
    }
}
