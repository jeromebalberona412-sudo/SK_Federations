<?php

namespace App\Modules\Dashboard\Services;

use App\Models\KabataanRegistration;
use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Services\ChairpersonFederationSyncService;
use App\Modules\Accounts\Services\FederationRosterService;
use App\Modules\AuditLog\Services\AuditLogQueryService;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardStatsService
{
    /** @var list<array{label: string, keys: list<string>}> */
    private const FEDERATION_OFFICER_SLOTS = [
        ['label' => 'President', 'keys' => ['President']],
        ['label' => 'Vice President', 'keys' => ['Vice President']],
        ['label' => 'Secretary', 'keys' => ['Secretary']],
        ['label' => 'Treasurer', 'keys' => ['Treasurer']],
        ['label' => 'Auditor', 'keys' => ['Auditor']],
        ['label' => 'PRO', 'keys' => ['PIO', 'PRO']],
        ['label' => 'Sergeant-at-Arms', 'keys' => ['Sergeant at Arms', 'Sergeant-at-Arms']],
    ];

    public function __construct(
        private readonly ChairpersonFederationSyncService $chairpersonFederationSyncService,
        private readonly FederationRosterService $federationRosterService,
    ) {
    }

    public function totalKabataanRegistered(?int $tenantId = null): int
    {
        return $this->activeKabataanQuery($tenantId)->count();
    }

    public function totalSkOfficials(?int $tenantId = null): int
    {
        if (! Schema::hasTable('users')) {
            return 0;
        }

        $query = User::query()
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->whereNull('deleted_at')
            ->whereHas('officialProfile')
            ->whereHas('officialProfile.terms', function ($termQuery): void {
                $termQuery
                    ->where('status', OfficialTerm::STATUS_ACTIVE)
                    ->whereDate('term_end', '>=', now()->startOfDay());
            });

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return (int) $query->count();
    }

    public function totalSkChairpersons(?int $tenantId = null): int
    {
        if (! Schema::hasTable('users')) {
            return 0;
        }

        $query = User::query()
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->whereNull('deleted_at')
            ->whereHas('officialProfile', function ($profileQuery): void {
                $this->chairpersonFederationSyncService->applyChairpersonPositionConstraint($profileQuery);
            })
            ->whereHas('officialProfile.terms', function ($termQuery): void {
                $termQuery
                    ->where('status', OfficialTerm::STATUS_ACTIVE)
                    ->whereDate('term_end', '>=', now()->startOfDay());
            });

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return (int) $query->count();
    }

    public function totalAuditLogs(?int $tenantId = null): int
    {
        if (! Schema::hasTable('admin_activity_logs')) {
            return 0;
        }

        return (int) app(AuditLogQueryService::class)->stats($tenantId)['total_logs'];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function sexDistribution(?int $tenantId = null): array
    {
        return $this->sexDistributionFromRecords($this->activeKabataanRecords($tenantId));
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function sexDistributionOfficials(?int $tenantId = null): array
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('official_profiles')) {
            return ['labels' => ['Male', 'Female'], 'values' => [0, 0]];
        }

        $query = User::query()
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->whereNull('deleted_at')
            ->whereHas('officialProfile')
            ->whereHas('officialProfile.terms', function ($termQuery): void {
                $termQuery
                    ->where('status', OfficialTerm::STATUS_ACTIVE)
                    ->whereDate('term_end', '>=', now()->startOfDay());
            })
            ->with('officialProfile:id,user_id,sex');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $male = 0;
        $female = 0;

        foreach ($query->get() as $user) {
            $sex = mb_strtolower(trim((string) ($user->officialProfile?->sex ?? '')));

            if (str_contains($sex, 'female')) {
                $female++;
            } elseif (str_contains($sex, 'male')) {
                $male++;
            }
        }

        return [
            'labels' => ['Male', 'Female'],
            'values' => [$male, $female],
        ];
    }

    /**
     * @return list<array{rank: int, barangay: string, count: int}>
     */
    public function topBarangaysByYouth(?int $tenantId = null, int $limit = 5): array
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return [];
        }

        $records = $this->activeKabataanQuery($tenantId)
            ->with('barangay:id,name')
            ->get(['id', 'barangay_id', 'form_data']);

        $counts = [];

        foreach ($records as $record) {
            $formData = is_array($record->form_data) ? $record->form_data : [];
            $barangay = $record->barangay?->name ?? $this->formValue($formData, 'barangay');

            if ($barangay === '—') {
                continue;
            }

            $counts[$barangay] = ($counts[$barangay] ?? 0) + 1;
        }

        arsort($counts);

        $result = [];
        $rank = 1;

        foreach (array_slice($counts, 0, $limit, true) as $barangay => $count) {
            $result[] = [
                'rank' => $rank++,
                'barangay' => $barangay,
                'count' => $count,
            ];
        }

        return $result;
    }

    /**
     * @return Collection<int, Barangay>
     */
    public function getBarangays(?int $tenantId): Collection
    {
        if ($tenantId === null) {
            return collect();
        }

        return Barangay::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return list<array{position: string, name: string, barangay: string}>
     */
    public function federationOfficers(?int $tenantId): array
    {
        if ($tenantId === null || ! Schema::hasTable('official_profiles')) {
            return $this->emptyFederationOfficers();
        }

        $assigned = $this->federationRosterService
            ->federationRosterQuery($tenantId)
            ->with(['officialProfile', 'barangay:id,name'])
            ->get()
            ->filter(fn (User $user): bool => filled($user->officialProfile?->federation_position))
            ->keyBy(fn (User $user): string => (string) $user->officialProfile?->federation_position);

        $officers = [];

        foreach (self::FEDERATION_OFFICER_SLOTS as $slot) {
            $match = null;

            foreach ($slot['keys'] as $key) {
                if ($assigned->has($key)) {
                    $match = $assigned->get($key);
                    break;
                }
            }

            $officers[] = [
                'position' => $slot['label'],
                'name' => $match ? $this->formatOfficialName($match) : 'Vacant',
                'barangay' => $match?->barangay?->name ?? '—',
            ];
        }

        return $officers;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<KabataanRegistration>
     */
    private function activeKabataanQuery(?int $tenantId)
    {
        $query = KabataanRegistration::query()
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'email_verified', 'password_set']);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if (Schema::hasColumn('kabataan_registrations', 'evaluation_status')) {
            $query->where(function ($evaluationQuery): void {
                $evaluationQuery->whereNull('evaluation_status')
                    ->orWhereIn('evaluation_status', ['active', 'Auto Approved']);
            });
        }

        return $query;
    }

    /**
     * @return Collection<int, KabataanRegistration>
     */
    private function activeKabataanRecords(?int $tenantId): Collection
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return collect();
        }

        return $this->activeKabataanQuery($tenantId)
            ->get(['id', 'form_data']);
    }

    /**
     * @param  Collection<int, KabataanRegistration>  $records
     * @return array{labels: list<string>, values: list<int>}
     */
    private function sexDistributionFromRecords(Collection $records): array
    {
        $male = 0;
        $female = 0;

        foreach ($records as $record) {
            $formData = is_array($record->form_data) ? $record->form_data : [];
            $sex = mb_strtolower($this->formValue($formData, 'sex'));

            if (str_contains($sex, 'female')) {
                $female++;
            } elseif (str_contains($sex, 'male')) {
                $male++;
            }
        }

        return [
            'labels' => ['Male', 'Female'],
            'values' => [$male, $female],
        ];
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function formValue(array $formData, string $key): string
    {
        $value = $formData[$key] ?? '—';

        if (is_array($value)) {
            $value = $value[0] ?? '—';
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : '—';
    }

    private function formatOfficialName(User $user): string
    {
        $profile = $user->officialProfile;

        if ($profile !== null) {
            $first = trim((string) $profile->first_name);
            $middle = $profile->middle_name ? mb_substr(trim((string) $profile->middle_name), 0, 1).'.' : '';
            $last = trim((string) $profile->last_name);
            $suffix = ($profile->suffix && $profile->suffix !== 'None') ? (string) $profile->suffix : '';

            $formatted = $last !== '' ? strtoupper($last).', '.$first : $first;
            if ($middle !== '') {
                $formatted .= ' '.$middle;
            }
            if ($suffix !== '') {
                $formatted .= ' '.$suffix;
            }

            if (trim($formatted) !== '') {
                return trim($formatted);
            }
        }

        return trim((string) ($user->name ?: '—'));
    }

    /**
     * @return list<array{position: string, name: string, barangay: string}>
     */
    private function emptyFederationOfficers(): array
    {
        return array_map(
            fn (array $slot): array => [
                'position' => $slot['label'],
                'name' => 'Vacant',
                'barangay' => '—',
            ],
            self::FEDERATION_OFFICER_SLOTS,
        );
    }
}
