<?php

namespace App\Modules\KabataanMonitoring\Controllers;

use App\Modules\KabataanMonitoring\Services\KabataanMonitoringService;
use App\Modules\KabataanMonitoring\Services\KkProfilingFederationUpdateService;
use App\Modules\Profile\Models\Barangay;
use App\Services\BarangayLogoUrlService;
use App\Modules\Shared\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KabataanMonitoringController extends Controller
{
    public function __construct(
        private readonly KabataanMonitoringService $service,
        private readonly KkProfilingFederationUpdateService $updateService,
    ) {
    }

    public function index(Request $request): View
    {
        return view('kabataan_monitoring::index', [
            'user' => $request->user(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->listAll(),
            'years' => $this->service->registrationYears(),
        ]);
    }

    public function questionnaire(int $id): JsonResponse
    {
        $html = $this->service->renderQuestionnaireHtml($id);

        if ($html === null) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        return response()->json([
            'html' => $html,
            'data' => $this->updateService->editPayload($id),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->destroy($request->user(), $id);

        return response()->json(['message' => 'Kabataan record deleted.']);
    }

    public function editData(int $id): JsonResponse
    {
        $payload = $this->updateService->editPayload($id);

        if ($payload === null) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        return response()->json(['data' => $payload]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'custom_suffix' => ['nullable', 'string', 'max:5'],
            'email' => ['nullable', 'email', 'max:254'],
            'contact_number' => ['nullable', 'string', 'max:15'],
            'age' => ['nullable', 'integer', 'min:15', 'max:30'],
            'birthday' => ['nullable', 'string', 'max:20'],
            'sex' => ['nullable', 'in:Male,Female'],
            'purok_zone' => ['nullable', 'string', 'max:100'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'youth_classification' => ['nullable', 'string', 'max:80'],
            'youth_age_group' => ['nullable', 'string', 'max:80'],
            'work_status' => ['nullable', 'string', 'max:80'],
            'education' => ['nullable', 'string', 'max:80'],
            'sk_voter' => ['nullable', 'string', 'max:10'],
            'national_voter' => ['nullable', 'string', 'max:10'],
            'sk_voted' => ['nullable', 'string', 'max:10'],
            'kk_assembly' => ['nullable', 'string', 'max:10'],
            'kk_times' => ['nullable', 'string', 'max:40'],
            'kk_reason' => ['nullable', 'string', 'max:120'],
            'facebook' => ['nullable', 'string', 'max:80'],
            'facebook_profile_url' => ['nullable', 'string', 'max:80'],
            'group_chat' => ['nullable', 'string', 'max:10'],
        ]);

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
        $barangayId = Barangay::query()->where('name', $barangayName)->value('id');
        $logoUrl = $barangayId ? app(BarangayLogoUrlService::class)->resolve((int) $barangayId) : null;

        // Purok/Zone filter should show all configured zones even if there are no KK records yet.
        $purokZones = [];
        if ($barangayId && Schema::hasTable('barangay_zones')) {
            $purokZones = DB::table('barangay_zones')
                ->where('barangay_id', (int) $barangayId)
                ->where('status', 'active')
                ->orderBy('name')
                ->pluck('name')
                ->filter()
                ->map(fn ($name) => (string) $name)
                ->values()
                ->all();
        }

        return view('kabataan_monitoring::barangay-detail', [
            'user' => $request->user(),
            'barangay' => $barangayName,
            'barangayLogoUrl' => $logoUrl,
            'registrationYears' => $this->service->registrationYears($barangayName),
            'purokZones' => $purokZones,
        ]);
    }

    public function batchPrint(Request $request): View
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $sheets = [];
        foreach (array_values(array_unique($validated['ids'])) as $id) {
            $html = $this->service->renderQuestionnaireHtml((int) $id);
            if ($html !== null) {
                $sheets[] = $html;
            }
        }

        abort_if($sheets === [], 404, 'No KK profiling records found for print.');

        return view('kabataan_monitoring::print-batch', [
            'sheets' => $sheets,
        ]);
    }
}
