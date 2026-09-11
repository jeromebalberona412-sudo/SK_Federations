<?php

namespace App\Modules\Dashboard\Controllers;

use App\Modules\Accounts\Support\SkOfficialTermDates;
use App\Modules\BarangayMonitoring\Services\BarangayMonitoringService;
use App\Modules\Dashboard\Services\DashboardStatsService;
use App\Modules\Dashboard\Services\KkProfilingChartService;
use App\Modules\Dashboard\Services\SkFedDashboardActivityService;
use App\Modules\Shared\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $dashboardStatsService,
        private readonly KkProfilingChartService $kkProfilingChartService,
        private readonly SkFedDashboardActivityService $dashboardActivityService,
        private readonly BarangayMonitoringService $barangayMonitoringService,
    ) {}

    public function index(Request $request): View
    {
        $tenantId = $request->user()?->tenant_id;
        $barangays = $this->dashboardStatsService->getBarangays($tenantId);

        try {
            $termDefaults = SkOfficialTermDates::leadershipDefaults((int) ($tenantId ?? 0));
        } catch (\Throwable $e) {
            report($e);
            $termDefaults = [
                'term_start' => SkOfficialTermDates::defaultStart(),
                'term_end' => SkOfficialTermDates::defaultEnd(),
            ];
        }

        $termStart = $termDefaults['term_start'] ?? SkOfficialTermDates::defaultStart();
        $termEnd = $termDefaults['term_end'] ?? SkOfficialTermDates::defaultEnd();
        $today = now()->timezone(config('app.timezone', 'Asia/Manila'))->toDateString();
        $dataEarliest = $this->earliestDashboardDataDate($tenantId);
        // Default range: President term start → today (capped by term end).
        $dateFrom = $termStart;
        $dateTo = $today <= $termEnd ? $today : $termEnd;
        $dashboardYear = (int) substr((string) $dateFrom, 0, 4);
        $user = $request->user();
        $showDateAll = $dataEarliest !== null && $dataEarliest < $termStart;

        try {
            $statCards = $this->dashboardStatsService->dashboardStatCards($tenantId, $dateFrom, $dateTo);
        } catch (\Throwable $e) {
            report($e);
            $statCards = [
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
            'totalKabataanRegistered' => $statCards['total_kabataan_registered'] ?? 0,
            'totalSkOfficials' => $statCards['total_sk_officials'] ?? 0,
            'totalSkChairpersons' => $statCards['total_sk_chairpersons'] ?? 0,
            'totalAuditLogs' => $statCards['total_audit_logs'] ?? 0,
            'totalBarangaysAbyipSubmitted' => $abyipOverview['stats']['submitted'] ?? 0,
            'sexDistribution' => $safe(fn () => $this->dashboardStatsService->sexDistribution($tenantId, $dateFrom, $dateTo), []),
            'sexDistributionOfficials' => $safe(fn () => $this->dashboardStatsService->sexDistributionOfficials($tenantId, $dateFrom, $dateTo), []),
            'sexDistributionFederations' => $safe(fn () => $this->dashboardStatsService->sexDistributionFederations($tenantId, $dateFrom, $dateTo), []),
            'employmentStatusDistribution' => $safe(fn () => $this->dashboardStatsService->employmentStatusDistribution($tenantId, $dateFrom, $dateTo), []),
            'federationOfficers' => $safe(fn () => $this->dashboardStatsService->federationOfficers($tenantId), collect()),
            'topBarangays' => $safe(fn () => $this->dashboardStatsService->topBarangaysByYouth($tenantId, 5, $dateFrom, $dateTo), collect()),
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
            'skfed:dash:stats:v5:%s:%s:%s',
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
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);
        $defaults = $tenantId > 0 ? SkOfficialTermDates::leadershipDefaults($tenantId) : null;
        $termStart = $defaults['term_start'] ?? null;
        $termEnd = $defaults['term_end'] ?? null;
        $today = now()->timezone(config('app.timezone', 'Asia/Manila'))->toDateString();
        $earliest = $this->earliestDashboardDataDate($request->user()?->tenant_id);

        if ($dateFrom === '') {
            $dateFrom = $termStart;
        }
        if ($dateTo === '') {
            $dateTo = ($termEnd && $today > $termEnd) ? $termEnd : $today;
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

        if ($dateFrom !== null && $dateTo !== null && $dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            return [$dateTo, $dateFrom];
        }

        return [$dateFrom ?: null, $dateTo ?: null];
    }

    private function earliestDashboardDataDate(?int $tenantId): ?string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('kabataan_registrations')) {
            return null;
        }

        try {
            $query = \Illuminate\Support\Facades\DB::table('kabataan_registrations')
                ->whereNull('deleted_at');

            if (\Illuminate\Support\Facades\Schema::hasColumn('kabataan_registrations', 'archived_at')) {
                $query->whereNull('archived_at');
            }

            $minCreated = $query->min('created_at');
            if (! $minCreated) {
                return null;
            }

            return \Carbon\Carbon::parse($minCreated)->toDateString();
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
