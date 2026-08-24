<?php

namespace App\Modules\AuditLog\Controllers;

use App\Modules\AuditLog\Services\AuditLogQueryService;
use App\Modules\Shared\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogQueryService $queryService,
    ) {
    }

    public function index(Request $request): View
    {
        $tenantId = $request->user()?->tenant_id;

        return view('auditlogs::auditlogs', [
            'filterOptions' => $this->queryService->filterOptions($tenantId),
            'stats' => $this->queryService->stats($tenantId),
            'routes' => [
                'data' => route('auditlogs.data'),
                'stats' => route('auditlogs.stats'),
                'recent' => route('auditlogs.recent'),
                'exportCsv' => route('auditlogs.export.csv'),
                'exportExcel' => route('auditlogs.export.excel'),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $requestedPerPage = (int) $request->integer('per_page', 100);
        $allowedPerPage = [10, 25, 50, 100, 500, 1000];
        $perPage = in_array($requestedPerPage, $allowedPerPage, true) ? $requestedPerPage : 100;
        $page = max(1, (int) $request->integer('page', 1));

        $paginator = $this->queryService->paginate(
            $this->extractFilters($request),
            $tenantId,
            $perPage,
            $page,
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->queryService->stats($request->user()?->tenant_id),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $limit = max(1, min(20, (int) $request->integer('limit', 10)));

        return response()->json([
            'data' => $this->queryService->recentActivity($request->user()?->tenant_id, $limit),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        return $this->streamExport($request, 'csv');
    }

    public function exportExcel(Request $request): Response
    {
        $rows = $this->queryService->exportRows(
            $this->extractFilters($request),
            $request->user()?->tenant_id,
        );

        $html = view('auditlogs::export-excel', ['rows' => $rows])->render();

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit-logs-'.now()->format('Y-m-d-His').'.xls"',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    protected function streamExport(Request $request, string $format): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $tenantId = $request->user()?->tenant_id;
        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters, $tenantId) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Date & Time',
                'User',
                'Role',
                'Barangay',
                'Event Type',
                'Action',
                'Entity Type',
                'Entity ID',
                'IP Address',
            ]);

            $this->queryService
                ->exportRows($filters, $tenantId)
                ->each(function (array $row) use ($handle) {
                    fputcsv($handle, [
                        $row['created_at'],
                        $row['user_name'],
                        $row['role'],
                        $row['barangay'],
                        $row['event_type'],
                        $row['action'],
                        $row['entity_type'],
                        $row['entity_id'],
                        $row['ip_address'],
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->get('search', '')),
            'date_from' => trim((string) $request->get('date_from', '')),
            'date_to' => trim((string) $request->get('date_to', '')),
            'user_id' => $request->get('user_id'),
            'role' => trim((string) $request->get('role', '')),
            'barangay_id' => $request->get('barangay_id'),
            'event_type' => trim((string) $request->get('event_type', '')),
            'action' => trim((string) $request->get('action', '')),
            'module' => trim((string) $request->get('module', '')),
        ];
    }
}
