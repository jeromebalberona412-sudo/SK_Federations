<?php

namespace App\Modules\Turnover\Controllers;

use App\Modules\Dashboard\Services\DashboardStatsService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Turnover\Models\FederationTurnover;
use App\Modules\Turnover\Requests\StoreTurnoverOfficerRequest;
use App\Modules\Turnover\Services\FederationTermDetectionService;
use App\Modules\Turnover\Services\TurnoverBatchTemplateService;
use App\Modules\Turnover\Services\TurnoverCompletionService;
use App\Modules\Turnover\Services\TurnoverRegistrationService;
use App\Modules\Turnover\Services\TurnoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TurnoverController extends Controller
{
    public function __construct(
        private readonly TurnoverService $turnoverService,
        private readonly TurnoverRegistrationService $registrationService,
        private readonly TurnoverCompletionService $completionService,
        private readonly FederationTermDetectionService $termDetectionService,
        private readonly DashboardStatsService $dashboardStatsService,
        private readonly TurnoverBatchTemplateService $batchTemplateService,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorize('viewAny', FederationTurnover::class);

        $context = $this->turnoverService->dashboardContext($user);
        $tenantId = (int) $user->tenant_id;
        $activeTurnover = $context['active_turnover'];

        if ($activeTurnover) {
            $activeTurnover->load('registrations');
        }

        return view('turnover::index', [
            'user' => $user,
            'context' => $context,
            'barangays' => $this->dashboardStatsService->getBarangays($tenantId),
            'activeTurnover' => $activeTurnover,
            'progress' => $context['progress'],
            'cssVersion' => @filemtime(app_path('Modules/Turnover/assets/css/turnover.css')) ?: time(),
            'jsVersion' => @filemtime(app_path('Modules/Turnover/assets/js/turnover.js')) ?: time(),
        ]);
    }

    public function waiting(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! in_array($user->turnover_status, ['pending_confirmation', 'awaiting_setup'], true)) {
            return redirect()->route('dashboard');
        }

        return view('turnover::waiting', [
            'user' => $user,
            'cssVersion' => @filemtime(app_path('Modules/Turnover/assets/css/turnover.css')) ?: time(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $this->authorize('start', FederationTurnover::class);

        $turnover = $this->turnoverService->startTurnover($request->user(), $request);

        return response()->json([
            'message' => 'Federation turnover started successfully.',
            'turnover' => $turnover,
            'redirect' => route('dashboard'),
        ]);
    }

    public function remindLater(Request $request): JsonResponse
    {
        $this->authorize('start', FederationTurnover::class);

        $this->turnoverService->remindLater($request->user());

        return response()->json([
            'message' => 'Turnover notice dismissed. We will remind you later.',
        ]);
    }

    public function register(StoreTurnoverOfficerRequest $request): JsonResponse
    {
        $user = $request->user();
        $turnover = $this->termDetectionService->activeTurnoverForTenant((int) $user->tenant_id);

        if ($turnover === null) {
            return response()->json(['message' => 'No active turnover found.'], 422);
        }

        $this->authorize('register', $turnover);

        $validated = $request->validated();

        $turnover = $this->registrationService->registerIncomingOfficers(
            $turnover,
            $validated['president'],
            $validated['vice_president'],
            $user,
            $request,
        );

        return response()->json([
            'message' => 'Incoming Federation Officers registered successfully. Account setup emails have been sent.',
            'turnover' => $turnover,
        ]);
    }

    public function complete(Request $request, FederationTurnover $turnover): JsonResponse
    {
        $this->authorize('complete', $turnover);

        $request->validate([
            'confirmation' => ['required', 'string', 'in:Confirm'],
        ]);

        $turnover = $this->completionService->completeTurnover($turnover, $request->user(), $request);

        return response()->json([
            'message' => 'Federation turnover completed successfully.',
            'turnover' => $turnover,
            'redirect' => route('dashboard'),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($this->turnoverService->dashboardContext($user));
    }

    public function downloadBatchTemplate(): BinaryFileResponse
    {
        $this->authorize('viewAny', FederationTurnover::class);

        return $this->batchTemplateService->downloadResponse();
    }
}
