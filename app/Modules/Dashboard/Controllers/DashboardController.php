<?php

namespace App\Modules\Dashboard\Controllers;

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
        $dashboardYear = $this->dashboardStatsService->dashboardYear($tenantId);
        $dateFrom = sprintf('%04d-01-01', $dashboardYear);
        $dateTo = now()->toDateString();
        $user = $request->user();
        $statCards = $this->dashboardStatsService->dashboardStatCards($tenantId, $dateFrom, $dateTo);
        $abyipOverview = $this->barangayMonitoringService->dashboardAbyipOverview(10, $dateFrom, $dateTo);

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

        return view('dashboard::dashboard', [
            'user' => $user,
            'totalKabataanRegistered' => $statCards['total_kabataan_registered'],
            'totalSkOfficials' => $statCards['total_sk_officials'],
            'totalSkChairpersons' => $statCards['total_sk_chairpersons'],
            'totalAuditLogs' => $statCards['total_audit_logs'],
            'totalBarangaysAbyipSubmitted' => $abyipOverview['stats']['submitted'],
            'sexDistribution' => $this->dashboardStatsService->sexDistribution($tenantId, $dateFrom, $dateTo),
            'sexDistributionOfficials' => $this->dashboardStatsService->sexDistributionOfficials($tenantId, $dateFrom, $dateTo),
            'federationOfficers' => $this->dashboardStatsService->federationOfficers($tenantId),
            'topBarangays' => $this->dashboardStatsService->topBarangaysByYouth($tenantId, 5, $dateFrom, $dateTo),
            'barangays' => $barangays,
            'kkBarangayOptions' => $barangays
                ->map(fn ($barangay) => ['id' => $barangay->id, 'name' => $barangay->name])
                ->values()
                ->all(),
            'recentActivity' => $this->dashboardActivityService->recentActivity($tenantId, 12, $dateFrom, $dateTo),
            'upcomingEvents' => $this->dashboardActivityService->upcomingCalendarNotes(6, $dateFrom, $dateTo),
            'todayReminder' => $this->dashboardActivityService->todayCalendarNote(),
            'todayLabel' => now()->timezone(config('app.timezone', 'Asia/Manila'))->format('F j, Y | l'),
            'dashboardYear' => $dashboardYear,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'abyipOverview' => $abyipOverview,
            'kkProfilingInitial' => $user
                ? $this->kkProfilingChartService->getChartData($user, $kkInitialRequest)
                : null,
            'purokChartInitial' => $user
                ? $this->kkProfilingChartService->getPurokChartData($user, $purokInitialRequest)
                : null,
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
            'skfed:dash:stats:v3:%s:%s:%s',
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

        if ($dateFrom === '') {
            $dateFrom = null;
        }
        if ($dateTo === '') {
            $dateTo = null;
        }

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            return [$dateTo, $dateFrom];
        }

        return [$dateFrom, $dateTo];
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
