<?php

namespace App\Modules\Dashboard\Controllers;

use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Support\SkOfficialTermDates;
use App\Modules\BarangayMonitoring\Services\BarangayMonitoringService;
use App\Modules\Dashboard\Services\DashboardStatsService;
use App\Modules\Dashboard\Services\YouthDemographicsService;
use App\Modules\Dashboard\Services\KkProfilingChartService;
use App\Modules\Dashboard\Services\SkFedDashboardActivityService;
use App\Modules\Shared\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $dashboardStatsService,
        private readonly KkProfilingChartService $kkProfilingChartService,
        private readonly SkFedDashboardActivityService $dashboardActivityService,
        private readonly BarangayMonitoringService $barangayMonitoringService,
        private readonly YouthDemographicsService $youthDemographicsService,
    ) {}

    public function index(Request $request): View
    {
        $tenantId = $request->user()?->tenant_id;

        try {
            $barangays = $this->dashboardStatsService->getBarangays($tenantId);
        } catch (\Throwable $e) {
            report($e);
            $barangays = collect();
        }

        [$termStart, $termEnd] = $this->resolveTermBoundsForDashboard($tenantId);
        [$dateFromCarbon, $dateToCarbon] = $this->defaultCalendarYearRange($termStart, $termEnd);
        $dateFrom = $dateFromCarbon->toDateString();
        $dateTo = $dateToCarbon->toDateString();
        $dataEarliest = $this->earliestDashboardDataDate($tenantId);
        $dashboardYear = (int) $dateFromCarbon->year;
        $user = $request->user();
        $showDateAll = $dataEarliest !== null && $dataEarliest < $termStart;

        try {
            $statCards = $this->dashboardStatsService->dashboardStatCards($tenantId, $dateFrom, $dateTo);
        } catch (\Throwable $e) {
            report($e);
            $statCards = [
                'total_kk' => 0,
                'kk_approved' => 0,
                'kk_pending' => 0,
                'total_programs' => 0,
                'total_kabataan_registered' => 0,
                'total_sk_officials' => 0,
                'total_sk_chairpersons' => 0,
                'total_audit_logs' => 0,
            ];
        }

        try {
            $abyipOverview = $this->barangayMonitoringService->dashboardAbyipOverview(10, $dateFrom, $dateTo);
        } catch (\Throwable $e) {
            report($e);
            $abyipOverview = [
                'stats' => [
                    'submitted' => 0,
                    'pending' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                    'fiscal_year' => $dashboardYear,
                    'fiscal_year_label' => 'CY '.$dashboardYear,
                    'total_barangays' => 0,
                ],
                'recent' => [],
            ];
        }

        $kkInitialRequest = new Request([
            'barangay_id' => 'all',
            'period' => 'monthly',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $purokInitialRequest = new Request([
            'barangay_id' => 'all',
            'purok' => 'all',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $kkProfilingInitial = null;
        $purokChartInitial = null;
        if ($user) {
            try {
                $kkProfilingInitial = $this->kkProfilingChartService->getChartData($user, $kkInitialRequest);
            } catch (\Throwable $e) {
                report($e);
            }
            try {
                $purokChartInitial = $this->kkProfilingChartService->getPurokChartData($user, $purokInitialRequest);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $safe = function (callable $callback, $fallback = null) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                report($e);

                return $fallback;
            }
        };

        return view('dashboard::dashboard', [
            'user' => $user,
            'totalKkProfiles' => $statCards['total_kk'] ?? 0,
            'kkApproved' => $statCards['kk_approved'] ?? 0,
            'kkPending' => $statCards['kk_pending'] ?? 0,
            'totalPrograms' => $statCards['total_programs'] ?? 0,
            'totalKabataanRegistered' => $statCards['total_kabataan_registered'] ?? 0,
            'totalSkOfficials' => $statCards['total_sk_officials'] ?? 0,
            'totalSkChairpersons' => $statCards['total_sk_chairpersons'] ?? 0,
            'totalAuditLogs' => $statCards['total_audit_logs'] ?? 0,
            'totalBarangaysAbyipSubmitted' => $abyipOverview['stats']['submitted'] ?? 0,
            'sexDistribution' => $safe(fn () => $this->dashboardStatsService->sexDistribution($tenantId, $dateFrom, $dateTo), []),
            'sexDistributionOfficials' => $safe(fn () => $this->dashboardStatsService->sexDistributionOfficials($tenantId, $dateFrom, $dateTo), []),
            'sexDistributionFederations' => $safe(fn () => $this->dashboardStatsService->sexDistributionFederations($tenantId, $dateFrom, $dateTo), []),
            'employmentStatusDistribution' => $safe(fn () => $this->dashboardStatsService->employmentStatusDistribution($tenantId, $dateFrom, $dateTo), []),
            'youthDemographics' => $safe(
                fn () => $this->youthDemographicsService->aggregate($tenantId, $dateFrom, $dateTo),
                $this->youthDemographicsService->emptyPayload()
            ),
            'federationOfficers' => $safe(fn () => $this->dashboardStatsService->federationOfficers($tenantId), []),
            'topBarangays' => $safe(fn () => $this->dashboardStatsService->topBarangaysByYouth($tenantId, 5, $dateFrom, $dateTo), []),
            'barangays' => $barangays,
            'kkBarangayOptions' => $barangays
                ->map(fn ($barangay) => ['id' => $barangay->id, 'name' => $barangay->name])
                ->values()
                ->all(),
            'recentActivity' => $safe(fn () => $this->dashboardActivityService->recentActivity($tenantId, 12, $dateFrom, $dateTo), []),
            'upcomingEvents' => $safe(fn () => $this->dashboardActivityService->upcomingCalendarNotes(6, $dateFrom, $dateTo), []),
            'todayReminder' => $safe(fn () => $this->dashboardActivityService->todayCalendarNote(), null),
            'todayLabel' => now()->timezone(config('app.timezone', 'Asia/Manila'))->format('F j, Y | l'),
            'dashboardYear' => $dashboardYear,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'termStart' => $termStart,
            'termEnd' => $termEnd,
            'dataEarliest' => $dataEarliest,
            'showDateAll' => $showDateAll,
            'abyipOverview' => $abyipOverview,
            'kkProfilingInitial' => $kkProfilingInitial,
            'purokChartInitial' => $purokChartInitial,
        ]);
    }

    public function recentActivities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:50'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
        ]);

        $tenantId = $request->user()?->tenant_id;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        [$dateFrom, $dateTo] = $this->resolveFeedDates($request);

        return response()->json(
            $this->dashboardActivityService->paginatedActivities($tenantId, $page, $perPage, $dateFrom, $dateTo)
        );
    }

    public function feed(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        [$dateFrom, $dateTo] = $this->resolveFeedDates($request);
        $live = $this->dashboardLivePayload($tenantId, $dateFrom, $dateTo);

        return response()->json([
            'today_reminder' => $this->dashboardActivityService->todayCalendarNote(),
            ...$live,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        [$dateFrom, $dateTo] = $this->resolveFeedDates($request);
        $cacheKey = sprintf(
            'skfed:dash:stats:v10:%s:%s:%s',
            $tenantId === null ? 'all' : (string) $tenantId,
            $dateFrom ?? 'none',
            $dateTo ?? 'none'
        );

        $payload = Cache::remember(
            $cacheKey,
            20,
            fn (): array => $this->dashboardLivePayload($tenantId, $dateFrom, $dateTo)
        );

        return response()->json($payload);
    }

    /**
     * @return array{
     *     stats: array<string, int>,
     *     sex_distribution: array{labels: list<string>, values: list<int>},
     *     sex_distribution_officials: array{labels: list<string>, values: list<int>},
     *     abyip_overview: array<string, mixed>,
     *     date_from: ?string,
     *     date_to: ?string
     * }
     */
    private function dashboardLivePayload(?int $tenantId, ?string $dateFrom, ?string $dateTo): array
    {
        $statCards = $this->dashboardStatsService->dashboardStatCards($tenantId, $dateFrom, $dateTo);
        $abyipOverview = $this->barangayMonitoringService->dashboardAbyipOverview(10, $dateFrom, $dateTo);

        return [
            'stats' => [
                'total_kk' => $statCards['total_kk'],
                'kk_approved' => $statCards['kk_approved'],
                'kk_pending' => $statCards['kk_pending'],
                'total_programs' => $statCards['total_programs'],
                'total_kabataan_registered' => $statCards['total_kabataan_registered'],
                'total_sk_officials' => $statCards['total_sk_officials'],
                'total_sk_chairpersons' => $statCards['total_sk_chairpersons'],
                'total_barangays_abyip_submitted' => (int) ($abyipOverview['stats']['submitted'] ?? 0),
                'total_audit_logs' => $statCards['total_audit_logs'],
            ],
            'sex_distribution' => $this->dashboardStatsService->sexDistribution($tenantId, $dateFrom, $dateTo),
            'sex_distribution_officials' => $this->dashboardStatsService->sexDistributionOfficials($tenantId, $dateFrom, $dateTo),
            'sex_distribution_federations' => $this->dashboardStatsService->sexDistributionFederations($tenantId, $dateFrom, $dateTo),
            'employment_status_distribution' => $this->dashboardStatsService->employmentStatusDistribution($tenantId, $dateFrom, $dateTo),
            'youth_demographics' => $this->youthDemographicsService->aggregate($tenantId, $dateFrom, $dateTo),
            'abyip_overview' => $abyipOverview,
            'top_barangays' => $this->dashboardStatsService->topBarangaysByYouth($tenantId, 5, $dateFrom, $dateTo),
            'recent_activity' => $this->dashboardActivityService->recentActivity($tenantId, 12, $dateFrom, $dateTo),
            'upcoming_events' => $this->dashboardActivityService->upcomingCalendarNotes(6, $dateFrom, $dateTo),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveFeedDates(Request $request): array
    {
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));
        $rangeMode = strtolower(trim((string) $request->input('range_mode', 'term')));
        $tenantId = $request->user()?->tenant_id;
        [$termStart, $termEnd] = $this->resolveTermBoundsForDashboard($tenantId);
        $today = now()->timezone(config('app.timezone', 'Asia/Manila'))->toDateString();
        $earliest = $this->earliestDashboardDataDate($tenantId);

        if ($dateFrom === '' || $dateTo === '') {
            [$calendarFrom, $calendarTo] = $this->defaultCalendarYearRange($termStart, $termEnd);
            if ($dateFrom === '') {
                $dateFrom = $calendarFrom->toDateString();
            }
            if ($dateTo === '') {
                $dateTo = $calendarTo->toDateString();
            }
        }

        if ($rangeMode !== 'all') {
            if ($termStart && $dateFrom !== '' && $dateFrom < $termStart) {
                $dateFrom = $termStart;
            }
            if ($termEnd && $dateTo !== '' && $dateTo > $termEnd) {
                $dateTo = $termEnd;
            }
        } else {
            if ($earliest && $dateFrom !== '' && $dateFrom < $earliest) {
                $dateFrom = $earliest;
            }
            if ($dateTo !== '' && $dateTo > $today) {
                $dateTo = $today;
            }
        }

        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            return [$dateTo, $dateFrom];
        }

        return [$dateFrom ?: null, $dateTo ?: null];
    }

    /**
     * Default From/To = January 1 → December 31 of the current year,
     * clamped into official_terms.term_start / term_end so the values stay selectable.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function defaultCalendarYearRange(string $termStart, string $termEnd): array
    {
        $tz = config('app.timezone', 'Asia/Manila');
        $currentYear = (int) Carbon::now($tz)->year;
        $min = Carbon::createFromFormat('Y-m-d', $termStart, $tz)->startOfDay();
        $max = Carbon::createFromFormat('Y-m-d', $termEnd, $tz)->endOfDay();

        $useYear = $currentYear;
        if ($useYear < (int) $min->year) {
            $useYear = (int) $min->year;
        }
        if ($useYear > (int) $max->year) {
            $useYear = (int) $max->year;
        }

        $from = Carbon::create($useYear, 1, 1, 0, 0, 0, $tz)->startOfDay();
        $to = Carbon::create($useYear, 12, 31, 23, 59, 59, $tz)->endOfDay();

        if ($from->lt($min)) {
            $from = $min->copy();
        }
        if ($to->gt($max)) {
            $to = $max->copy();
        }
        if ($from->gt($to)) {
            $from = $min->copy();
            $to = $max->copy();
        }

        return [$from, $to];
    }

    /**
     * Selectable date-picker bounds from official_terms.term_start / term_end.
     * ACTIVE rows first; otherwise any term dates in the tenant.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveTermBoundsForDashboard(?int $tenantId): array
    {
        if (! Schema::hasTable('official_terms')) {
            return $this->leadershipTermFallback($tenantId);
        }

        try {
            $query = OfficialTerm::query()
                ->whereNotNull('term_start')
                ->whereNotNull('term_end');

            if ($tenantId !== null && $tenantId > 0) {
                $query->whereHas('officialProfile.user', function ($userQuery) use ($tenantId): void {
                    $userQuery->where('tenant_id', $tenantId);
                });
            }

            $activeBounds = (clone $query)
                ->where('status', OfficialTerm::STATUS_ACTIVE)
                ->selectRaw('MIN(term_start) as oldest_start, MAX(term_end) as latest_end')
                ->first();

            $oldestStart = $this->termDateString($activeBounds?->oldest_start ?? null);
            $latestEnd = $this->termDateString($activeBounds?->latest_end ?? null);

            if ($oldestStart === null || $latestEnd === null) {
                $anyBounds = (clone $query)
                    ->selectRaw('MIN(term_start) as oldest_start, MAX(term_end) as latest_end')
                    ->first();
                $oldestStart = $this->termDateString($anyBounds?->oldest_start ?? null);
                $latestEnd = $this->termDateString($anyBounds?->latest_end ?? null);
            }
        } catch (\Throwable $e) {
            report($e);

            return $this->leadershipTermFallback($tenantId);
        }

        if ($oldestStart === null || $latestEnd === null || $oldestStart > $latestEnd) {
            return $this->leadershipTermFallback($tenantId);
        }

        return [$oldestStart, $latestEnd];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function leadershipTermFallback(?int $tenantId): array
    {
        try {
            $defaults = SkOfficialTermDates::leadershipDefaults((int) ($tenantId ?? 0));
        } catch (\Throwable $e) {
            report($e);
            $defaults = [
                'term_start' => SkOfficialTermDates::defaultStart(),
                'term_end' => SkOfficialTermDates::defaultEnd(),
            ];
        }

        return [
            $defaults['term_start'] ?? SkOfficialTermDates::defaultStart(),
            $defaults['term_end'] ?? SkOfficialTermDates::defaultEnd(),
        ];
    }

    private function termDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        $raw = substr(trim((string) $value), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1 ? $raw : null;
    }

    private function earliestDashboardDataDate(?int $tenantId): ?string
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return null;
        }

        try {
            $query = DB::table('kabataan_registrations')
                ->whereNull('deleted_at');

            if (Schema::hasColumn('kabataan_registrations', 'archived_at')) {
                $query->whereNull('archived_at');
            }

            $minCreated = $query->min('created_at');
            if (! $minCreated) {
                return null;
            }

            return Carbon::parse($minCreated)->toDateString();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function kkProfilingData(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->kkProfilingChartService->getChartData($request->user(), $request),
        ]);
    }

    public function purokChartData(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->kkProfilingChartService->getPurokChartData($request->user(), $request),
        ]);
    }
}
