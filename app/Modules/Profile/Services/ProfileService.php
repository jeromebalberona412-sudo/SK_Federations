<?php

namespace App\Modules\Profile\Services;

use App\Modules\Authentication\Services\BootstrapSkFedAdminService;
use App\Modules\Profile\Models\Barangay;
use App\Modules\Profile\Models\OfficialProfile;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProfileService
{
    /**
     * @return array<string, string>
     */
    public function getDisplayData(User $user, ?OfficialProfile $officialProfile, ?Barangay $barangay): array
    {
        $nameFallback = $this->splitFullName($user->name);
        $isBootstrapAdmin = Str::lower((string) $user->email) === BootstrapSkFedAdminService::bootstrapEmailNormalized();

        return [
            'first_name' => $this->displayName($isBootstrapAdmin ? 'Admin' : ($officialProfile?->first_name ?? $nameFallback['first_name'])),
            'middle_name' => $this->displayName($isBootstrapAdmin ? 'Admin' : ($officialProfile?->middle_name ?? $nameFallback['middle_name'])),
            'last_name' => $this->displayName($officialProfile?->last_name ?? $nameFallback['last_name']),
            'suffix' => $this->displaySuffix($officialProfile?->suffix),
            'sex' => $this->displayValue($officialProfile?->sex ?? null),
            'birthdate' => $this->formatDate($officialProfile?->date_of_birth),
            'contact_number' => $this->displayValue($officialProfile?->contact_number),
            'position' => $this->displayPosition($isBootstrapAdmin ? 'Admin' : $this->resolveProfilePosition($officialProfile)),
            'region' => $this->displayValue($officialProfile?->region ?? 'IV-A CALABARZON'),
            'province' => $this->displayValue($officialProfile?->province ?? 'Laguna'),
            'municipality' => $this->displayValue($officialProfile?->municipality ?? 'Santa Cruz'),
            'barangay' => $this->displayValue($barangay?->name),
            'term_start' => $this->resolveTermStart($officialProfile),
            'term_end' => $this->resolveTermEnd($officialProfile),
            'email' => $this->displayValue($user->email),
        ];
    }

    private function resolveTermStart(?OfficialProfile $officialProfile): string
    {
        $term = $this->resolveLatestTerm($officialProfile);

        return $term?->term_start ? $this->formatDate($term->term_start) : 'N/A';
    }

    private function resolveTermEnd(?OfficialProfile $officialProfile): string
    {
        $term = $this->resolveLatestTerm($officialProfile);

        return $term?->term_end ? $this->formatDate($term->term_end) : 'N/A';
    }

    private function resolveLatestTerm(?OfficialProfile $officialProfile): ?object
    {
        if ($officialProfile === null || ! Schema::hasTable('official_terms')) {
            return null;
        }

        if (! Schema::hasColumn('official_terms', 'official_profile_id')) {
            return null;
        }

        $baseQuery = fn () => \Illuminate\Support\Facades\DB::table('official_terms')
            ->where('official_profile_id', $officialProfile->id);

        if (Schema::hasColumn('official_terms', 'status')) {
            $active = $baseQuery()->where('status', 'ACTIVE')->orderByDesc('term_start')->first();
            if ($active !== null) {
                return $active;
            }
        }

        return $baseQuery()->orderByDesc('term_start')->first();
    }

    private function displayValue(?string $value): string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : 'N/A';
    }

    private function displayName(?string $value): string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? mb_strtoupper($trimmed, 'UTF-8') : 'N/A';
    }

    private function displaySuffix(?string $suffix): string
    {
        $trimmed = trim((string) $suffix);

        if ($trimmed === '' || strcasecmp($trimmed, 'none') === 0 || strcasecmp($trimmed, 'n/a') === 0) {
            return 'N/A';
        }

        return $trimmed;
    }

    private function displayPosition(?string $position): string
    {
        $trimmed = trim((string) $position);

        return $trimmed !== '' ? $trimmed : 'N/A';
    }

    private function resolveProfilePosition(?OfficialProfile $officialProfile): ?string
    {
        if ($officialProfile === null) {
            return null;
        }

        $federationPosition = trim((string) ($officialProfile->federation_position ?? ''));
        if ($federationPosition !== '') {
            return $federationPosition;
        }

        return $officialProfile->position;
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('F d, Y');
        } catch (\Throwable) {
            return 'N/A';
        }
    }

    /**
     * @return array{first_name: ?string, middle_name: ?string, last_name: ?string}
     */
    private function splitFullName(?string $fullName): array
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        if ($parts === []) {
            return ['first_name' => null, 'middle_name' => null, 'last_name' => null];
        }

        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'middle_name' => null, 'last_name' => null];
        }

        if (count($parts) === 2) {
            return ['first_name' => $parts[0], 'middle_name' => null, 'last_name' => $parts[1]];
        }

        return [
            'first_name' => $parts[0],
            'middle_name' => implode(' ', array_slice($parts, 1, -1)),
            'last_name' => $parts[count($parts) - 1],
        ];
    }
}
