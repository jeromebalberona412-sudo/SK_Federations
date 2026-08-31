<?php

namespace App\Modules\Dashboard\Controllers;

use App\Modules\BarangayMonitoring\Services\BarangayMonitoringService;
use App\Modules\Dashboard\Services\DashboardStatsService;
use App\Modules\Dashboard\Services\KkProfilingChartService;
use App\Modules\Dashboard\Services\SkFedDashboardActivityService;
use App\Modules\Shared\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $user = $request->user();

        $kkInitialRequest = new Request([
            'barangay_id' => 'all',
            'period' => 'monthly',
            'year' => $dashboardYear,
        ]);

        $purokInitialRequest = new Request([
            'barangay_id' => 'all',
            'purok' => 'all',
            'year' => 'all',
        ]);

        return view('dashboard::dashboard', [
            'user' => $user,
            'totalKabataanRegistered' => $this->dashboardStatsService->totalKabataanRegistered($tenantId),
            'totalSkOfficials' => $this->dashboardStatsService->totalSkOfficials($tenantId),
            'totalSkChairpersons' => $this->dashboardStatsService->totalSkChairpersons($tenantId),
            'totalAuditLogs' => $this->dashboardStatsService->totalAuditLogs($tenantId),
            'totalBarangaysAbyipSubmitted' => $this->barangayMonitoringService->countBarangaysWithAbyipSubmission(),
            'sexDistribution' => $this->dashboardStatsService->sexDistribution($tenantId),
            'sexDistributionOfficials' => $this->dashboardStatsService->sexDistributionOfficials($tenantId),
            'federationOfficers' => $this->dashboardStatsService->federationOfficers($tenantId),
            'topBarangays' => $this->dashboardStatsService->topBarangaysByYouth($tenantId),
            'barangays' => $barangays,
            'kkBarangayOptions' => $barangays
                ->map(fn ($barangay) => ['id' => $barangay->id, 'name' => $barangay->name])
                ->values()
                ->all(),
            'recentActivity' => $this->dashboardActivityService->recentActivity($tenantId),
            'upcomingEvents' => $this->dashboardActivityService->upcomingCalendarNotes(),
            'todayReminder' => $this->dashboardActivityService->todayCalendarNote(),
            'todayLabel' => now()->timezone(config('app.timezone', 'Asia/Manila'))->format('F j, Y | l'),
            'dashboardYear' => $dashboardYear,
            'abyipOverview' => $this->barangayMonitoringService->dashboardAbyipOverview(10),
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
        ]);

        $tenantId = $request->user()?->tenant_id;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);

        return response()->json(
            $this->dashboardActivityService->paginatedActivities($tenantId, $page, $perPage)
        );
    }

    public function feed(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;

        return response()->json([
            'recent_activity' => $this->dashboardActivityService->recentActivity($tenantId),
            'upcoming_events' => $this->dashboardActivityService->upcomingCalendarNotes(),
            'today_reminder' => $this->dashboardActivityService->todayCalendarNote(),
            'stats' => [
                'total_kabataan_registered' => $this->dashboardStatsService->totalKabataanRegistered($tenantId),
                'total_sk_officials' => $this->dashboardStatsService->totalSkOfficials($tenantId),
                'total_sk_chairpersons' => $this->dashboardStatsService->totalSkChairpersons($tenantId),
                'total_barangays_abyip_submitted' => $this->barangayMonitoringService->countBarangaysWithAbyipSubmission(),
                'total_audit_logs' => $this->dashboardStatsService->totalAuditLogs($tenantId),
            ],
        ]);
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
