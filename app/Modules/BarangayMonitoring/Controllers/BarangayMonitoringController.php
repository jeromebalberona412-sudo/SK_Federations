<?php

namespace App\Modules\BarangayMonitoring\Controllers;

use App\Modules\BarangayMonitoring\Services\AbyipSubmissionScheduleService;
use App\Modules\BarangayMonitoring\Services\BarangayMonitoringService;
use App\Modules\Shared\Controllers\Controller;
use App\Services\BarangayLogoUrlService;
use App\Services\SkFedPageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BarangayMonitoringController extends Controller
{
    public function __construct(
        private readonly BarangayMonitoringService $monitoringService,
        private readonly AbyipSubmissionScheduleService $scheduleService,
    ) {}

    public function index(Request $request): View
    {
        $pageCache = app(SkFedPageCache::class);
        $payload = $pageCache->remember('barangay.monitoring.index', function () {
            $logoService = app(BarangayLogoUrlService::class);
            $catalog = $this->barangayCatalog();
            $slugToName = [];
            foreach ($catalog as $slug => $row) {
                $slugToName[$slug] = (string) ($row['name'] ?? $this->monitoringService->nameFromSlug($slug) ?? '');
            }

            $idBySlug = $this->monitoringService->resolveBarangayIdsBySlug($slugToName);
            $barangayIds = array_values(array_filter(array_map('intval', $idBySlug)));
            $statusMap = $this->monitoringService->resolveAbyipStatusMap($barangayIds);
            $logoMap = $logoService->resolveMany($barangayIds);

            $barangays = [];
            foreach ($catalog as $slug => $row) {
                $barangayId = $idBySlug[$slug] ?? null;
                $abyipStatus = ($barangayId && isset($statusMap[$barangayId]))
                    ? $statusMap[$barangayId]
                    : [
                        'status' => 'not_submitted',
                        'submitted_by' => null,
                        'submitted_by_role' => null,
                        'latest_report' => null,
                    ];

                $barangays[] = array_merge($row, [
                    'barangay_id' => $barangayId,
                    'status' => $abyipStatus['status'],
                    'submitted_by' => $abyipStatus['submitted_by'],
                    'submitted_by_role' => $abyipStatus['submitted_by_role'] ?? null,
                    'logo_url' => $barangayId ? ($logoMap[$barangayId] ?? null) : null,
                ]);
            }

            return [
                'barangays' => $barangays,
                'stats' => $this->buildStats($barangays),
            ];
        });

        // Always read schedule live — never serve a deleted/stale schedule id from page cache.
        return view('barangay_monitoring::index', [
            'user' => $request->user(),
            'barangays' => $payload['barangays'],
            'stats' => $payload['stats'],
            'abyipSchedule' => $this->scheduleService->currentSchedule(),
            'abyipSchedules' => $this->scheduleService->listSchedules()->values()->all(),
            'canCreateAbyipSchedule' => $this->scheduleService->canCreateForCurrentYear(),
        ]);
    }

    public function show(Request $request, string $barangay): View
    {
        $catalog = $this->barangayCatalog();
        $detail = $this->barangayDetails();

        if (! isset($catalog[$barangay], $detail[$barangay])) {
            abort(404);
        }

        $barangayId = $this->monitoringService->resolveBarangayId($barangay);
        $barangayData = array_merge($catalog[$barangay], $detail[$barangay]);
        $barangayData['barangay_id'] = $barangayId;
        $barangayData['logo_url'] = $barangayId ? app(BarangayLogoUrlService::class)->resolve($barangayId) : null;
        $barangayData['abyip']['reports'] = $this->monitoringService->getAbyipReports($barangayId);
        $accomplishments = $this->monitoringService->getApprovedAccomplishments($barangayId);
        $barangayData['accomplishments'] = $accomplishments;
        $barangayData['accomplishment_years'] = collect($accomplishments)
            ->pluck('year')
            ->unique()
            ->sortDesc()
            ->map(fn ($year) => (string) $year)
            ->values()
            ->all();
        $barangayData['accomplishment_terms'] = collect($accomplishments)
            ->pluck('term')
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
        $barangayData['abyip_schedule'] = $this->scheduleService->currentSchedule();

        $complianceStatus = $this->calculateComplianceStatus($barangayId);
        $barangayData['compliance_status'] = $complianceStatus['status'];
        $barangayData['compliance_details'] = $complianceStatus['details'];
        $barangayData['submitted_by'] = $complianceStatus['submitted_by'] ?? null;
        $barangayData['submitted_by_role'] = $complianceStatus['submitted_by_role'] ?? null;
        $barangayData['warnings'] = $this->getWarningsForBarangay($complianceStatus['status']);

        return view('barangay_monitoring::show', [
            'user' => $request->user(),
            'barangayData' => $barangayData,
        ]);
    }

    public function scheduleList(): JsonResponse
    {
        return response()->json([
            'data' => $this->scheduleService->listSchedules()->values(),
            'current' => $this->scheduleService->currentSchedule(),
        ]);
    }

    public function scheduleStore(Request $request): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->create($request->user(), $request->all());
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        app(SkFedPageCache::class)->bump('barangay.monitoring.index');
        $this->invalidateOfficialsAbyipCaches();

        return response()->json([
            'message' => 'ABYIP submission schedule created.',
            'data' => $schedule,
        ], 201);
    }

    public function scheduleUpdate(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->update($request->user(), $id, $request->all());
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        app(SkFedPageCache::class)->bump('barangay.monitoring.index');
        $this->invalidateOfficialsAbyipCaches();

        return response()->json([
            'message' => 'ABYIP submission schedule updated.',
            'data' => $schedule,
        ]);
    }

    public function scheduleExtend(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'new_deadline' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $schedule = $this->scheduleService->extendDeadline(
                $request->user(),
                $id,
                $validated['new_deadline'],
                $validated['reason'] ?? null
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        app(SkFedPageCache::class)->bump('barangay.monitoring.index');
        $this->invalidateOfficialsAbyipCaches();

        return response()->json([
            'message' => 'ABYIP submission deadline extended.',
            'data' => $schedule,
        ]);
    }

    public function scheduleAllowResubmission(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->allowResubmission($request->user(), $id);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        app(SkFedPageCache::class)->bump('barangay.monitoring.index');
        $this->invalidateOfficialsAbyipCaches();

        return response()->json([
            'message' => 'ABYIP resubmission is now allowed for barangays that have not submitted.',
            'data' => $schedule,
        ]);
    }

    public function scheduleCancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $schedule = $this->scheduleService->cancel($request->user(), $id, $validated['reason'] ?? null);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        app(SkFedPageCache::class)->bump('barangay.monitoring.index');
        $this->invalidateOfficialsAbyipCaches();

        return response()->json([
            'message' => 'ABYIP submission schedule cancelled.',
            'data' => $schedule,
        ]);
    }

    public function scheduleDestroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->scheduleService->destroy($request->user(), $id);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        app(SkFedPageCache::class)->bump('barangay.monitoring.index');
        $this->invalidateOfficialsAbyipCaches();

        return response()->json([
            'message' => 'ABYIP submission schedule deleted.',
        ]);
    }

    /**
     * Shared Redis/file cache with SK Officials — bump ABYIP page payloads after schedule changes.
     */
    private function invalidateOfficialsAbyipCaches(): void
    {
        try {
            $key = 'skoff:pagever:abyip:global';
            Cache::forever($key, ((int) Cache::get($key, 1)) + 1);
        } catch (\Throwable) {
            // Cache store may be unavailable in local/dev; schedule reads are still live.
        }
    }

    private function buildStats(array $barangays): array
    {
        $count = count($barangays);
        $approved = count(array_filter($barangays, fn ($item) => ($item['status'] ?? '') === 'approved'));
        $pending = count(array_filter($barangays, fn ($item) => ($item['status'] ?? '') === 'pending'));
        $rejected = count(array_filter($barangays, fn ($item) => ($item['status'] ?? '') === 'rejected'));
        $notSubmitted = count(array_filter($barangays, fn ($item) => ($item['status'] ?? '') === 'not_submitted'));

        $submitted = $approved + $pending + $rejected;
        $submissionRate = $count > 0 ? round(($submitted / $count) * 100) : 0;
        $notSubmittedRate = $count > 0 ? round(($notSubmitted / $count) * 100) : 0;
        $abyipSubmittedCount = $this->monitoringService->countBarangaysWithAbyipSubmission();

        return [
            'total_barangays' => $count,
            'compliance_rate' => $submissionRate,
            'submission_rate' => $submissionRate,
            'compliant_count' => $approved,
            'non_compliance_rate' => $notSubmittedRate,
            'not_submitted_rate' => $notSubmittedRate,
            'non_compliant_count' => $notSubmitted + $rejected,
            'submitted_count' => $submitted,
            'abyip_submitted_count' => $abyipSubmittedCount,
            'approved_count' => $approved,
            'pending_count' => $pending,
            'rejected_count' => $rejected,
            'not_submitted_count' => $notSubmitted,
        ];
    }

    private function barangayCatalog(): array
    {
        $map = $this->monitoringService->slugToNameMap();
        $catalog = [];

        foreach ($map as $slug => $name) {
            $catalog[$slug] = [
                'slug' => $slug,
                'name' => $name,
                'status' => 'not_submitted',
                'reports' => 0,
                'on_time' => 0,
                'active_programs' => 0,
                'participation_rate' => 0,
                'last_update' => 'No updates yet',
                'sk_chairman' => 'Not assigned',
                'report_rate' => 0,
            ];
        }

        return $catalog;
    }

    private function barangayDetails(): array
    {
        $catalog = $this->barangayCatalog();
        $details = [];

        foreach ($catalog as $slug => $row) {
            $details[$slug] = [
                'municipality' => 'Santa Cruz, Laguna',
                'coverage' => '0 youth clusters',
                'total_kabataan' => 0,
                'verified_accounts' => 0,
                'latest_submission' => 'No submissions yet',
                'compliance_score' => 0,
                'programs' => [
                    'previous_programs' => [],
                    'current_programs' => [],
                    'future_programs' => [],
                ],
                'officials' => [],
                'system_data' => [
                    'report_cycles_submitted' => 0,
                    'pending_reports' => 0,
                    'last_audit_result' => 'No audit conducted yet',
                    'device_trust_rate' => '0%',
                    'top_issue' => 'No data available',
                ],
                'program_stats' => [
                    'total_youth_population' => 0,
                    'total_programs_created' => 0,
                    'total_ongoing' => 0,
                    'total_completed' => 0,
                    'total_participants' => 0,
                    'overall_performance' => 'No data',
                ],
                'performance_summary' => [
                    'completion_rate' => 0,
                    'attendance_rate' => 0,
                    'budget_efficiency' => 0,
                    'most_active_sector' => 'N/A',
                    'most_successful_program' => 'N/A',
                    'low_participation_count' => 0,
                ],
                'program_list' => [],
                'accomplishments' => [],
                'accomplishment_years' => [],
                'accomplishment_terms' => [],
                'abyip' => [
                    'budget_utilization' => 0,
                    'remaining_balance' => 0,
                    'project_count' => 0,
                    'reports' => [],
                ],
            ];
        }

        return $details;
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateComplianceStatus(?int $barangayId): array
    {
        $abyipStatus = $this->monitoringService->resolveAbyipStatus($barangayId);

        $details = match ($abyipStatus['status']) {
            'approved' => 'ABYIP report approved',
            'pending' => 'ABYIP report submitted and awaiting review',
            'rejected' => 'ABYIP report rejected',
            default => 'No ABYIP report submitted',
        };

        return [
            'status' => $abyipStatus['status'],
            'details' => $details,
            'submitted_by' => $abyipStatus['submitted_by'],
            'submitted_by_role' => $abyipStatus['submitted_by_role'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getWarningsForBarangay(string $complianceStatus): array
    {
        return [];
    }
}
