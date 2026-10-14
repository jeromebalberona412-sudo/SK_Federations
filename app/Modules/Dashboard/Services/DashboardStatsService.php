<?php

namespace App\Modules\Dashboard\Services;

use App\Models\KabataanRegistration;
use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Services\ChairpersonFederationSyncService;
use App\Modules\Accounts\Services\FederationRosterService;
use App\Modules\AuditLog\Services\AuditLogQueryService;
use App\Modules\KabataanMonitoring\Support\KkProfilingReviewStatus;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    /** @var list<string> */
    private const EMPLOYMENT_STATUS_OPTIONS = [
        'Employed',
        'Unemployed',
        'Self-Employed',
        'Currently looking for a Job',
        'Not Interested Looking for a Job',
    ];

    public function __construct(
        private readonly ChairpersonFederationSyncService $chairpersonFederationSyncService,
        private readonly FederationRosterService $federationRosterService,
    ) {}

    /** @var Collection<int, KabataanRegistration>|null */
    private ?Collection $cachedKabataanRecords = null;

    private ?int $cachedKabataanTenantId = null;

    /** @var array<string, Collection<int, KabataanRegistration>> */
    private array $datedActiveFormData = [];

    public function totalKabataanRegistered(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $query = $this->activeKabataanQuery($tenantId);
        $this->applyKabataanDateRange($query, $dateFrom, $dateTo);

        return (int) $query->count();
    }

    public function totalSkOfficials(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): int
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

        $this->applyCreatedAtDateRange($query, $dateFrom, $dateTo);

        return (int) $query->count();
    }

    public function totalSkChairpersons(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): int
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

        $this->applyCreatedAtDateRange($query, $dateFrom, $dateTo);

        return (int) $query->count();
    }

    public function totalAuditLogs(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        if (! Schema::hasTable('admin_activity_logs')) {
            return 0;
        }

        if ($dateFrom === null && $dateTo === null) {
            return (int) app(AuditLogQueryService::class)->stats($tenantId)['total_logs'];
        }

        return (int) app(AuditLogQueryService::class)->countInDateRange($tenantId, $dateFrom, $dateTo);
    }

    /**
     * @return array<string, int>
     */
    /**
     * Municipality-wide KK profiling counts (every barangay in the tenant).
     *
     * @return array{total_kk: int, approved: int, pending: int}
     */
    public function municipalityKkStats(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (! $tenantId || ! Schema::hasTable('kabataan_registrations')) {
            return ['total_kk' => 0, 'approved' => 0, 'pending' => 0];
        }

        $base = $this->tenantKabataanQuery($tenantId);

        $approvedQuery = KkProfilingReviewStatus::applyApprovedScope((clone $base)->whereNull('deleted_at'));
        $this->applyKabataanDateRange($approvedQuery, $dateFrom, $dateTo);

        $pendingQuery = (clone $base)->whereNull('deleted_at');
        KkProfilingReviewStatus::applyPendingScope($pendingQuery);
        $this->applyKabataanDateRange($pendingQuery, $dateFrom, $dateTo);

        $totalQuery = (clone $base)->whereNull('deleted_at');
        $this->applyColumnDateRange($totalQuery, 'submitted_at', $dateFrom, $dateTo);

        // One round trip. Each count keeps the same filters as the separate queries.
        $row = DB::query()
            ->selectSub((clone $totalQuery)->selectRaw('COUNT(*)'), 'total_kk')
            ->selectSub((clone $approvedQuery)->selectRaw('COUNT(*)'), 'approved')
            ->selectSub((clone $pendingQuery)->selectRaw('COUNT(*)'), 'pending')
            ->first();

        return [
            'total_kk' => (int) ($row->total_kk ?? 0),
            'approved' => (int) ($row->approved ?? 0),
            'pending' => (int) ($row->pending ?? 0),
        ];
    }

    /**
     * Scheduled programs across every barangay in the tenant.
     */
    public function totalPrograms(?int $tenantId = null): int
    {
        $query = $this->activeScheduleProgramsQuery($tenantId);

        return $query === null ? 0 : (int) $query->count();
    }

    /**
     * Aggregate active schedule programs within one Federation tenant.
     *
     * ScheduleProgram.status describes application availability, not program
     * implementation, so only the persisted open/closed states are reported.
     *
     * @return array{
     *     stats: array{total_programs:int, open_programs:int, closed_programs:int, upcoming_programs:int},
     *     status_chart: array{labels:list<string>, values:list<int>},
     *     barangay_chart: array{labels:list<string>, values:list<int>}
     * }
     */
    public function programDashboardData(?int $tenantId): array
    {
        $empty = [
            'stats' => [
                'total_programs' => 0,
                'open_programs' => 0,
                'closed_programs' => 0,
                'upcoming_programs' => 0,
            ],
            'status_chart' => ['labels' => [], 'values' => []],
            'barangay_chart' => ['labels' => [], 'values' => []],
        ];

        $query = $this->activeScheduleProgramsQuery($tenantId);
        if ($query === null) {
            return $empty;
        }

        $statusRows = (clone $query)
            ->selectRaw("LOWER(COALESCE(status, '')) AS program_status, COUNT(*) AS program_count")
            ->groupByRaw("LOWER(COALESCE(status, ''))")
            ->get();
        $statusCounts = $statusRows->mapWithKeys(
            fn (object $row): array => [(string) $row->program_status => (int) $row->program_count]
        );

        $total = (int) $statusRows->sum('program_count');
        $upcoming = Schema::hasColumn('schedule_programs', 'start_date')
            ? (int) (clone $query)->whereDate('start_date', '>', now()->toDateString())->count()
            : 0;

        $labels = [];
        $values = [];
        foreach ($statusCounts as $status => $count) {
            $labels[] = match ($status) {
                'open' => 'Open for Applications',
                'closed' => 'Closed to Applications',
                '' => 'Unspecified',
                default => ucfirst(str_replace('_', ' ', $status)),
            };
            $values[] = $count;
        }

        $barangayChart = ['labels' => [], 'values' => []];
        if (Schema::hasTable('barangays') && Schema::hasColumn('schedule_programs', 'barangay_id')) {
            $barangayRows = (clone $query)
                ->join('barangays as dashboard_barangays', 'dashboard_barangays.id', '=', 'schedule_programs.barangay_id')
                ->select('dashboard_barangays.id', 'dashboard_barangays.name')
                ->selectRaw('COUNT(*) AS program_count')
                ->groupBy('dashboard_barangays.id', 'dashboard_barangays.name')
                ->orderByDesc('program_count')
                ->orderBy('dashboard_barangays.name')
                ->limit(10)
                ->get();

            $barangayChart = [
                'labels' => $barangayRows->map(
                    fn (object $row): string => trim((string) $row->name) ?: 'Barangay '.$row->id
                )->all(),
                'values' => $barangayRows->map(fn (object $row): int => (int) $row->program_count)->all(),
            ];
        }

        return [
            'stats' => [
                'total_programs' => $total,
                'open_programs' => (int) ($statusCounts['open'] ?? 0),
                'closed_programs' => (int) ($statusCounts['closed'] ?? 0),
                'upcoming_programs' => $upcoming,
            ],
            'status_chart' => ['labels' => $labels, 'values' => $values],
            'barangay_chart' => $barangayChart,
        ];
    }

    private function activeScheduleProgramsQuery(?int $tenantId): ?\Illuminate\Database\Query\Builder
    {
        if ($tenantId === null || $tenantId <= 0 || ! Schema::hasTable('schedule_programs')) {
            return null;
        }

        $query = DB::table('schedule_programs');
        if (Schema::hasColumn('schedule_programs', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if (Schema::hasColumn('schedule_programs', 'is_archived')) {
            $query->where(function ($activeQuery): void {
                $activeQuery->whereNull('is_archived')->orWhereRaw('"is_archived" = false');
            });
        }

        if (Schema::hasColumn('schedule_programs', 'tenant_id')) {
            return $query->where('schedule_programs.tenant_id', $tenantId);
        }

        if (Schema::hasTable('barangays') && Schema::hasColumn('barangays', 'tenant_id')) {
            return $query->whereIn(
                'barangay_id',
                DB::table('barangays')->select('id')->where('tenant_id', $tenantId)
            );
        }

        return null;
    }

    public function dashboardStatCards(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $kkStats = $this->municipalityKkStats($tenantId, $dateFrom, $dateTo);
        $programs = $this->programDashboardData($tenantId);

        return [
            'total_kk' => $kkStats['total_kk'],
            'kk_approved' => $kkStats['approved'],
            'kk_pending' => $kkStats['pending'],
            'total_programs' => $programs['stats']['total_programs'],
            'programs' => $programs,
            'total_kabataan_registered' => $this->totalKabataanRegistered($tenantId, $dateFrom, $dateTo),
            'total_sk_officials' => $this->totalSkOfficials($tenantId, $dateFrom, $dateTo),
            'total_sk_chairpersons' => $this->totalSkChairpersons($tenantId, $dateFrom, $dateTo),
            'total_audit_logs' => $this->totalAuditLogs($tenantId, $dateFrom, $dateTo),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function sexDistribution(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return ['labels' => ['Male', 'Female'], 'values' => [0, 0]];
        }

        return $this->sexDistributionFromRecords(
            $this->datedActiveFormDataRecords($tenantId, $dateFrom, $dateTo)
        );
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function sexDistributionOfficials(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return $this->sexDistributionFromOfficialUsers(
            User::ROLE_SK_OFFICIAL,
            $tenantId,
            $dateFrom,
            $dateTo,
            requireActiveTerm: true
        );
    }

    /**
     * Sex distribution for SK Federation accounts (role sk_fed).
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function sexDistributionFederations(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return $this->sexDistributionFromOfficialUsers(
            User::ROLE_SK_FED,
            $tenantId,
            $dateFrom,
            $dateTo,
            requireActiveTerm: false
        );
    }

    /**
     * Municipality-wide Kabataan employment (work_status) distribution.
     *
     * @return array{labels: list<string>, values: list<int>, total: int}
     */
    public function employmentStatusDistribution(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $labels = self::EMPLOYMENT_STATUS_OPTIONS;
        $counts = array_fill_keys($labels, 0);

        if (! Schema::hasTable('kabataan_registrations')) {
            return [
                'labels' => $labels,
                'values' => array_values($counts),
                'total' => 0,
            ];
        }

        $records = $this->datedActiveFormDataRecords($tenantId, $dateFrom, $dateTo);
        $surveyStatusById = $this->surveyWorkStatusByRegistrationId($records->pluck('id'));

        foreach ($records as $record) {
            $formData = is_array($record->form_data) ? $record->form_data : [];
            $surveyStatus = $surveyStatusById[(int) $record->id] ?? null;
            $status = $this->normalizeEmploymentStatus(
                $surveyStatus ?? $this->formValue($formData, 'work_status')
            );
            if ($status !== null) {
                $counts[$status]++;
            }
        }

        return [
            'labels' => $labels,
            'values' => array_map(static fn (string $label): int => (int) $counts[$label], $labels),
            'total' => (int) array_sum($counts),
        ];
    }

    /**
     * @return list<array{rank: int, barangay: string, count: int}>
     */
    public function topBarangaysByYouth(?int $tenantId = null, int $limit = 5, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return [];
        }

        $query = $this->activeKabataanQuery($tenantId);
        $this->applyKabataanDateRange($query, $dateFrom, $dateTo);
        $youthRows = (clone $query)->select([
            'kabataan_registrations.id',
            'kabataan_registrations.barangay_id',
        ]);
        $groups = DB::query()
            ->fromSub($youthRows, 'youth_rows')
            ->leftJoin('barangays as youth_barangays', 'youth_barangays.id', '=', 'youth_rows.barangay_id')
            ->select('youth_rows.barangay_id')
            ->selectRaw('youth_barangays.name as barangay_name')
            ->selectRaw('COUNT(*) as youth_count')
            ->groupBy('youth_rows.barangay_id', 'youth_barangays.name')
            ->get();

        $counts = [];
        $hasMissingName = false;
        foreach ($groups as $group) {
            if ($group->barangay_name === null) {
                $hasMissingName = true;

                continue;
            }

            $barangay = (string) $group->barangay_name;
            if ($barangay === '—') {
                continue;
            }

            $counts[$barangay] = ($counts[$barangay] ?? 0) + (int) $group->youth_count;
        }

        if ($hasMissingName) {
            $missingIds = DB::query()
                ->fromSub((clone $query)->select([
                    'kabataan_registrations.id',
                    'kabataan_registrations.barangay_id',
                ]), 'youth_rows')
                ->leftJoin('barangays as youth_barangays', 'youth_barangays.id', '=', 'youth_rows.barangay_id')
                ->whereNull('youth_barangays.name')
                ->pluck('youth_rows.id');
            $missingNames = KabataanRegistration::query()
                ->whereIn('id', $missingIds)
                ->get(['id', 'form_data']);

            foreach ($missingNames as $record) {
                $formData = is_array($record->form_data) ? $record->form_data : [];
                $barangay = $this->formValue($formData, 'barangay');

                if ($barangay === '—') {
                    continue;
                }

                $counts[$barangay] = ($counts[$barangay] ?? 0) + 1;
            }
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
        if ($tenantId === null || ! Schema::hasTable('barangays')) {
            return collect();
        }

        try {
            return Barangay::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name']);
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    public function dashboardYear(?int $tenantId): int
    {
        $currentYear = (int) now()->year;

        if (! Schema::hasTable('kabataan_registrations')) {
            return $currentYear;
        }

        $query = KabataanRegistration::query()->whereNull('deleted_at');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $latest = $query
            ->orderByRaw('COALESCE(reviewed_at, created_at) DESC')
            ->first(['reviewed_at', 'created_at']);

        $date = $latest?->reviewed_at ?? $latest?->created_at;

        return $date ? (int) $date->format('Y') : $currentYear;
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
     * @return Builder<KabataanRegistration>
     */
    private function tenantKabataanQuery(?int $tenantId)
    {
        $query = KabataanRegistration::query();

        if ($tenantId !== null && Schema::hasColumn('kabataan_registrations', 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyColumnDateRange(Builder $query, string $column, ?string $dateFrom, ?string $dateTo): void
    {
        [$from, $to] = $this->normalizeDateRange($dateFrom, $dateTo);
        if ($from === null || $to === null || ! Schema::hasColumn($query->getModel()->getTable(), $column)) {
            return;
        }

        $query->whereDate($column, '>=', $from)
            ->whereDate($column, '<=', $to);
    }

    /**
     * @return Builder<KabataanRegistration>
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
     * @param  Builder<Model>  $query
     */
    private function applyCreatedAtDateRange(Builder $query, ?string $dateFrom, ?string $dateTo): void
    {
        [$from, $to] = $this->normalizeDateRange($dateFrom, $dateTo);
        if ($from === null || $to === null) {
            return;
        }

        $query->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);
    }

    /**
     * @param  Builder<KabataanRegistration>  $query
     */
    private function applyKabataanDateRange(Builder $query, ?string $dateFrom, ?string $dateTo): void
    {
        [$from, $to] = $this->normalizeDateRange($dateFrom, $dateTo);
        if ($from === null || $to === null) {
            return;
        }

        $query->where(function ($rangeQuery) use ($from, $to): void {
            $rangeQuery->where(function ($reviewedQuery) use ($from, $to): void {
                $reviewedQuery->whereNotNull('reviewed_at')
                    ->whereDate('reviewed_at', '>=', $from)
                    ->whereDate('reviewed_at', '<=', $to);
            })->orWhere(function ($createdQuery) use ($from, $to): void {
                $createdQuery->whereNull('reviewed_at')
                    ->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to);
            });
        });
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function normalizeDateRange(?string $dateFrom, ?string $dateTo): array
    {
        $from = trim((string) $dateFrom);
        $to = trim((string) $dateTo);

        if ($from === '' && $to === '') {
            return [null, null];
        }

        if ($from === '') {
            $from = $to;
        }
        if ($to === '') {
            $to = $from;
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * @return Collection<int, KabataanRegistration>
     */
    private function datedActiveFormDataRecords(?int $tenantId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $key = ($tenantId === null ? 'all' : (string) $tenantId).'|'.($dateFrom ?? '').'|'.($dateTo ?? '');
        if (isset($this->datedActiveFormData[$key])) {
            return $this->datedActiveFormData[$key];
        }

        $query = $this->activeKabataanQuery($tenantId);
        $this->applyKabataanDateRange($query, $dateFrom, $dateTo);

        return $this->datedActiveFormData[$key] = $query->get(['id', 'form_data']);
    }

    /**
     * @return Collection<int, KabataanRegistration>
     */
    private function cachedActiveKabataanRecords(?int $tenantId): Collection
    {
        if ($this->cachedKabataanRecords !== null && $this->cachedKabataanTenantId === $tenantId) {
            return $this->cachedKabataanRecords;
        }

        if (! Schema::hasTable('kabataan_registrations')) {
            $this->cachedKabataanTenantId = $tenantId;
            $this->cachedKabataanRecords = collect();

            return $this->cachedKabataanRecords;
        }

        $this->cachedKabataanTenantId = $tenantId;
        $this->cachedKabataanRecords = $this->activeKabataanQuery($tenantId)
            ->with('barangay:id,name')
            ->get(['id', 'barangay_id', 'form_data']);

        return $this->cachedKabataanRecords;
    }

    /**
     * @return Collection<int, KabataanRegistration>
     */
    private function activeKabataanRecords(?int $tenantId): Collection
    {
        return $this->cachedActiveKabataanRecords($tenantId);
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
     * @return array{labels: list<string>, values: list<int>}
     */
    private function sexDistributionFromOfficialUsers(
        string $role,
        ?int $tenantId,
        ?string $dateFrom,
        ?string $dateTo,
        bool $requireActiveTerm
    ): array {
        if (! Schema::hasTable('users') || ! Schema::hasTable('official_profiles')) {
            return ['labels' => ['Male', 'Female'], 'values' => [0, 0]];
        }

        $query = User::query()
            ->where('role', $role)
            ->whereNull('deleted_at')
            ->whereHas('officialProfile')
            ->with('officialProfile:id,user_id,sex');

        if ($requireActiveTerm) {
            $query->whereHas('officialProfile.terms', function ($termQuery): void {
                $termQuery
                    ->where('status', OfficialTerm::STATUS_ACTIVE)
                    ->whereDate('term_end', '>=', now()->startOfDay());
            });
        }

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $this->applyCreatedAtDateRange($query, $dateFrom, $dateTo);

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
     * @param  array<string, mixed>  $formData
     */
    private function formValue(array $formData, string $key): string
    {
        $aliases = [
            'work_status' => ['work_status', 'workStatus', 'employment_status'],
        ];

        foreach ($aliases[$key] ?? [$key] as $candidate) {
            if (! array_key_exists($candidate, $formData)) {
                continue;
            }

            $value = $formData[$candidate];
            if (is_array($value)) {
                $value = $value[0] ?? '';
            }

            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        $value = $formData[$key] ?? '—';

        if (is_array($value)) {
            $value = $value[0] ?? '—';
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : '—';
    }

    /**
     * @param  Collection<int, mixed>  $registrationIds
     * @return array<int, string>
     */
    private function surveyWorkStatusByRegistrationId(Collection $registrationIds): array
    {
        $ids = $registrationIds
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if (
            $ids->isEmpty()
            || ! Schema::hasTable('kk_survey_responses')
            || ! Schema::hasColumn('kk_survey_responses', 'work_status')
        ) {
            return [];
        }

        try {
            return DB::table('kk_survey_responses')
                ->whereIn('kabataan_registration_id', $ids->all())
                ->whereNotNull('work_status')
                ->where('work_status', '!=', '')
                ->pluck('work_status', 'kabataan_registration_id')
                ->mapWithKeys(function ($status, $id): array {
                    $normalized = $this->normalizeEmploymentStatus((string) $status);

                    return $normalized === null ? [] : [(int) $id => $normalized];
                })
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    private function normalizeEmploymentStatus(string $status): ?string
    {
        $status = trim($status);
        if ($status === '' || $status === '—') {
            return null;
        }

        foreach (self::EMPLOYMENT_STATUS_OPTIONS as $option) {
            if (strcasecmp($status, $option) === 0) {
                return $option;
            }
        }

        return null;
    }

    private function formatOfficialName(User $user): string
    {
        $profile = $user->officialProfile;

        if ($profile !== null) {
            $first = trim((string) $profile->first_name);
            $middle = trim((string) ($profile->middle_name ?? ''));
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
