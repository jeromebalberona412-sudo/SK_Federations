<?php

namespace App\Modules\Barangay_ABYIP\Controllers;

use App\Modules\Barangay_ABYIP\Services\BarangayAbyipService;
use App\Modules\Shared\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BarangayAbyipController extends Controller
{
    public function __construct(private readonly BarangayAbyipService $service)
    {
    }

    public function index(Request $request): View
    {
        return view('barangay_abyip::barangay_abyip', [
            'user' => $request->user(),
        ]);
    }

    public function list(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->listSubmissions()->values(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->service->show($id),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
            ], 404);
        }
    }

    public function file(int $id): Response|JsonResponse
    {
        try {
            $pdf = $this->service->pdfFile($id);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
            ], 404);
        }

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['filename'].'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $submission = $this->service->approve($request->user(), $id);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'ABYIP submission approved.',
            'data' => $submission,
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $submission = $this->service->reject($request->user(), $id, $validated['reason']);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'ABYIP submission rejected.',
            'data' => $submission,
        ]);
    }

    public function revoke(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:100'],
        ]);

        try {
            $submission = $this->service->revoke($request->user(), $id, $validated['reason']);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'ABYIP approval revoked.',
            'data' => $submission,
        ]);
    }
}
