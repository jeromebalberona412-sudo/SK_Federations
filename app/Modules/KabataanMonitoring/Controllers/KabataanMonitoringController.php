<?php

namespace App\Modules\KabataanMonitoring\Controllers;

use App\Modules\KabataanMonitoring\Services\KabataanMonitoringDocumentService;
use App\Modules\KabataanMonitoring\Services\KabataanMonitoringExcelService;
use App\Modules\KabataanMonitoring\Services\KabataanMonitoringService;
use App\Modules\KabataanMonitoring\Services\KkProfilingReviewService;
use App\Modules\KabataanMonitoring\Services\KkProfilingFederationUpdateService;
use App\Modules\KabataanMonitoring\Support\KkProfilingReviewStatus;
use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Controllers\Controller;
use App\Requests\UpdateKkProfilingRequest;
use App\Rules\ValidEmailAddress;
use App\Services\BarangayLogoUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class KabataanMonitoringController extends Controller
{
    public function __construct(
        private readonly KabataanMonitoringService $service,
        private readonly KabataanMonitoringDocumentService $documentService,
        private readonly KabataanMonitoringExcelService $excelService,
        private readonly KkProfilingFederationUpdateService $updateService,
        private readonly KkProfilingReviewService $reviewService,
    ) {}

    public function index(Request $request): View
    {
        $tenantId = (int) $request->user()->tenant_id;

        return view('kabataan_monitoring::index', [
            'user' => $request->user(),
            'initialRecords' => $this->service->listAll(tenantId: $tenantId),
            'registrationYears' => $this->service->registrationYears(tenantId: $tenantId),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $barangay = trim((string) $request->query('barangay', ''));
        $barangayFilter = $barangay !== '' ? $barangay : null;
        $tenantId = (int) $request->user()->tenant_id;

        return response()->json([
            'data' => $this->service->listAll($barangayFilter, $tenantId, $barangayFilter !== null),
            'years' => $this->service->registrationYears($barangayFilter, $tenantId),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function questionnaire(Request $request, int $id): JsonResponse
    {
        $html = $this->service->renderQuestionnaireHtml($id, (int) $request->user()->tenant_id);

        if ($html === null) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $data = $this->updateService->editPayload($id, (int) $request->user()->tenant_id);
        if ($data !== null) {
            $data['monitoringDocument'] = $this->documentService->metadata($request->user(), $id);
        }

        return response()->json([
            'html' => $html,
            'data' => $data,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->destroy($request->user(), $id);

        return response()->json(['message' => 'Kabataan record deleted.']);
    }

    public function bulkApprove(Request $request, string $barangay): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);
        $user = $request->user();
        $barangayId = $this->barangayIdForUser($request, $barangay);

        DB::transaction(function () use ($validated, $user, $barangayId): void {
            foreach ($validated['ids'] as $id) {
                $this->reviewService->approve($user, (int) $id, $barangayId);
            }
        });

        $this->service->forgetListCache(
            urldecode($barangay),
            (int) $user->tenant_id,
        );

        return response()->json([
            'success' => true,
            'message' => count($validated['ids']).' KK Profiling record(s) approved.',
            'approved' => count($validated['ids']),
        ]);
    }

    public function bulkDestroy(Request $request, string $barangay): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);
        $user = $request->user();
        $barangayId = $this->barangayIdForUser($request, $barangay);
        $deleted = $this->service->destroySelected(
            $user,
            array_map('intval', $validated['ids']),
            $barangayId,
        );

        return response()->json([
            'success' => true,
            'message' => $deleted.' KK Profiling record(s) deleted.',
            'deleted' => $deleted,
        ]);
    }

    public function editData(Request $request, int $id): JsonResponse
    {
        $payload = $this->updateService->editPayload($id, (int) $request->user()->tenant_id);

        if ($payload === null) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $payload['monitoringDocument'] = $this->documentService->metadata($request->user(), $id);

        return response()->json(['data' => $payload])->header('Cache-Control', 'private, no-store');
    }

    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'extensions:pdf', 'max:10240'],
        ]);

        $document = $this->documentService->upload($request->user(), $id, $validated['file']);

        return response()->json([
            'success' => true,
            'message' => 'PDF document uploaded successfully.',
            'data' => [
                ...$document,
                'view_url' => route('api.kabataan-monitoring.document', ['id' => $id]),
                'download_url' => route('api.kabataan-monitoring.document.download', ['id' => $id]),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function viewDocument(Request $request, int $id): Response
    {
        return $this->documentService->response($request->user(), $id);
    }

    public function downloadDocument(Request $request, int $id): Response
    {
        return $this->documentService->response($request->user(), $id, true);
    }

    public function update(UpdateKkProfilingRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->updateService->update($request->user(), $id, $validated);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        $message = 'KK Profiling record updated.';
        if ($result['invite_sent']) {
            $message = 'KK Profiling record updated. An activation email was sent to the new address.';
        } elseif (! empty($result['invite_error'])) {
            $message = $result['invite_error'];
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'invite_sent' => $result['invite_sent'],
            'invite_error' => $result['invite_error'],
        ]);
    }

    public function show(Request $request, string $kabataan): View
    {
        return view('kabataan_monitoring::show', [
            'user' => $request->user(),
            'kabataan' => $kabataan,
        ]);
    }

    public function barangayDetail(Request $request, string $barangay): View
    {
        $barangayName = urldecode($barangay);
        $barangayRecord = Barangay::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('name', $barangayName)
            ->firstOrFail();
        $barangayId = (int) $barangayRecord->id;
        $logoUrl = $barangayId ? app(BarangayLogoUrlService::class)->resolve((int) $barangayId) : null;

        // Purok/Zone filter should show all configured zones even if there are no KK records yet.
        $purokZones = [];
        if ($barangayId && Schema::hasTable('barangay_zones')) {
            $zonesCacheKey = 'skfed:km:zones:v1:'.(int) $barangayId;
            $purokZones = Cache::remember($zonesCacheKey, 300, function () use ($barangayId) {
                return DB::table('barangay_zones')
                    ->where('barangay_id', (int) $barangayId)
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->pluck('name')
                    ->filter()
                    ->map(fn ($name) => (string) $name)
                    ->values()
                    ->all();
            });
        }

        return view('kabataan_monitoring::barangay-detail', [
            'user' => $request->user(),
            'barangay' => $barangayName,
            'barangayRecord' => $barangayRecord,
            'barangayLogoUrl' => $logoUrl,
            'registrationYears' => $this->service->registrationYears($barangayName, (int) $request->user()->tenant_id),
            'purokZones' => $purokZones,
            'initialRecords' => $this->service->listAll($barangayName, (int) $request->user()->tenant_id, true),
        ]);
    }

    public function showReviewSubmission(Request $request, int $id): View
    {
        return $this->reviewSubmissionView($request, $id, null, null);
    }

    public function showBarangayReviewSubmission(Request $request, string $barangay, int $id): View
    {
        $barangayId = $this->barangayIdForUser($request, $barangay);

        return $this->reviewSubmissionView($request, $id, $barangayId, urldecode($barangay));
    }

    public function approveReviewSubmission(Request $request, int $id): RedirectResponse
    {
        $this->reviewService->approve($request->user(), $id);

        return back()->with('success', 'KK Profiling submission approved.');
    }

    public function approveBarangayReviewSubmission(Request $request, string $barangay, int $id): RedirectResponse
    {
        $barangayId = $this->barangayIdForUser($request, $barangay);
        $this->reviewService->approve($request->user(), $id, $barangayId);

        return back()->with('success', 'KK Profiling submission approved.');
    }

    public function rejectReviewSubmission(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
            'rejection_remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->reviewService->reject(
            $request->user(),
            $id,
            $validated['rejection_reason'],
            $validated['rejection_remarks'] ?? null,
        );

        return back()->with('success', 'KK Profiling submission rejected.');
    }

    public function rejectBarangayReviewSubmission(Request $request, string $barangay, int $id): RedirectResponse
    {
        $barangayId = $this->barangayIdForUser($request, $barangay);
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
            'rejection_remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->reviewService->reject(
            $request->user(),
            $id,
            $validated['rejection_reason'],
            $validated['rejection_remarks'] ?? null,
            $barangayId,
        );

        return back()->with('success', 'KK Profiling submission rejected.');
    }

    public function reviewDocument(Request $request, int $id): Response
    {
        return $this->documentService->response($request->user(), $id, false, true);
    }

    public function downloadReviewDocument(Request $request, int $id): Response
    {
        return $this->documentService->response($request->user(), $id, true, true);
    }

    private function reviewSubmissionView(
        Request $request,
        int $id,
        ?int $barangayId,
        ?string $barangayName,
    ): View {
        $record = $this->reviewService->findSubmission($request->user(), $id, $barangayId);
        $questionnaireHtml = $this->service->renderQuestionnaireHtml(
            $id,
            (int) $request->user()->tenant_id,
            true,
            $barangayId,
        );
        abort_if($questionnaireHtml === null, 404, 'KK Profiling submission not found.');
        $status = in_array($request->query('status'), ['all', 'pending', 'approved', 'rejected'], true)
            ? (string) $request->query('status')
            : 'pending';
        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $backParams = ['status' => $status, 'page' => $page ?: 1];

        return view('kabataan_monitoring::review-submission', [
            'record' => $record,
            'barangayName' => $barangayName ?? $record->barangay?->name,
            'backUrl' => $barangayId !== null
                ? route('kabataan-monitoring.barangay', [
                    'barangay' => $barangayName ?? $record->barangay?->name,
                    ...$backParams,
                ])
                : route('kabataan-monitoring'),
            'statusLabel' => KkProfilingReviewStatus::labelFor($record),
            'questionnaireHtml' => $questionnaireHtml,
            'document' => $this->documentService->metadata($request->user(), $id, true),
        ]);
    }

    private function barangayIdForUser(Request $request, string $barangay): int
    {
        return (int) Barangay::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('name', urldecode($barangay))
            ->firstOrFail()
            ->id;
    }

    public function downloadBatchTemplate(Request $request, string $barangay): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $barangayRecord = Barangay::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('name', urldecode($barangay))
            ->firstOrFail();

        return $this->excelService->downloadTemplate($barangayRecord);
    }

    public function batchPrint(Request $request): View
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $sheets = $this->service->printSheets(
            array_map('intval', $validated['ids']),
            (int) $request->user()->tenant_id,
        );

        abort_if($sheets === [], 404, 'No KK profiling records found for print.');

        return view('kabataan_monitoring::print-batch', [
            'sheets' => $sheets,
        ]);
    }

    public function print(Request $request, int $id): View
    {
        $sheet = $this->service->printSheetData($id, (int) $request->user()->tenant_id);
        abort_if($sheet === null, 404, 'KK profiling record not found for print.');

        return view('kabataan_monitoring::print', [
            'sheet' => $sheet,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $contactNumber = trim((string) $request->input('contact_number', ''));
        if ($contactNumber === '' || $contactNumber === '09') {
            $request->merge(['contact_number' => null]);
        }

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => \App\Rules\ValidEmailAddress::profilingRules(),
            'contact_number' => ['nullable', 'string', 'max:15'],
            'age' => ['required', 'integer', 'min:15', 'max:30'],
            'birthday' => ['required', 'string', 'max:20'],
            'sex' => ['required', 'in:Male,Female'],
            'purok_zone' => ['required', 'string', 'max:100'],
            'civil_status' => ['required', 'string', 'max:50'],
            'youth_classification' => ['required', 'string', 'max:80'],
            'youth_age_group' => ['required', 'string', 'max:80'],
            'work_status' => ['required', 'string', 'max:80'],
            'education' => ['required', 'string', 'max:80'],
            'sk_voter' => ['required', 'string', 'max:10'],
            'national_voter' => ['required', 'string', 'max:10'],
            'sk_voted' => ['required', 'string', 'max:10'],
            'kk_assembly' => ['required', 'string', 'max:10'],
            'kk_times' => ['nullable', 'string', 'max:40'],
            'kk_reason' => ['nullable', 'string', 'max:120'],
            'facebook' => ['nullable', 'string', 'max:50'],
            'group_chat' => ['nullable', 'string', 'max:10'],
            'signature' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
        ]);

        // Add the store logic here using the service
        // This will be implemented in the KabataanMonitoringService
        $result = $this->service->store($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Kabataan record saved.',
            'data' => $result,
        ]);
    }

    public function batchUpload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'extensions:xlsx,xls,csv', 'max:25600'],
            'barangay' => ['required', 'string', 'max:120'],
            'edited_rows' => ['nullable', 'string', 'max:4194304'],
        ]);

        $barangay = Barangay::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('name', $validated['barangay'])
            ->firstOrFail();
        $result = $this->excelService->import(
            $request->user(),
            $barangay,
            $validated['file'],
            $validated['edited_rows'] ?? null,
        );
        $message = "Imported {$result['imported']} of {$result['total']} row(s); "
            ."{$result['duplicates']} duplicate(s) skipped and {$result['rejected']} rejected. "
            ."{$result['imported']} imported record(s) were approved.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result,
        ]);
    }
}
