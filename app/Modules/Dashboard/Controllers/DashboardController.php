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
    ) {
    }

    public function index(Request $request): View
    {
        $tenantId = $request->user()?->tenant_id;

        return view('dashboard::dashboard', [
            'user' => $request->user(),
            'totalKabataanRegistered' => $this->dashboardStatsService->totalKabataanRegistered($tenantId),
            'totalSkOfficials' => $this->dashboardStatsService->totalSkOfficials($tenantId),
            'totalSkChairpersons' => $this->dashboardStatsService->totalSkChairpersons($tenantId),
            'totalAuditLogs' => $this->dashboardStatsService->totalAuditLogs($tenantId),
            'totalBarangaysAbyipSubmitted' => $this->barangayMonitoringService->countBarangaysWithAbyipSubmission(),
            'sexDistribution' => $this->dashboardStatsService->sexDistribution($tenantId),
            'sexDistributionOfficials' => $this->dashboardStatsService->sexDistributionOfficials($tenantId),
            'federationOfficers' => $this->dashboardStatsService->federationOfficers($tenantId),
            'topBarangays' => $this->dashboardStatsService->topBarangaysByYouth($tenantId),
            'barangays' => $this->dashboardStatsService->getBarangays($tenantId),
            'kkBarangayOptions' => $this->dashboardStatsService->getBarangays($tenantId)
                ->map(fn ($barangay) => ['id' => $barangay->id, 'name' => $barangay->name])
                ->values()
                ->all(),
            'recentActivity' => $this->dashboardActivityService->recentActivity($tenantId),
            'upcomingEvents' => $this->dashboardActivityService->upcomingCalendarNotes(),
            'todayReminder' => $this->dashboardActivityService->todayCalendarNote(),
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

    public function kkProfilingData(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->kkProfilingChartService->getChartData($request->user(), $request),
        ]);
    }
}
