<?php

namespace App\Modules\Rejected_Abyip\Controllers;

use App\Modules\Rejected_Abyip\Services\RejectedAbyipService;
use App\Modules\Shared\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RejectedAbyipController extends Controller
{
    public function __construct(
        private readonly RejectedAbyipService $rejectedAbyipService,
    ) {}

    public function index(): View
    {
        return view('Rejected_Abyip::rejected-abyip', [
            'retentionDays' => RejectedAbyipService::RETENTION_DAYS,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $rows = $this->rejectedAbyipService->listRejected()
            ->when($search !== '', function ($collection) use ($search) {
                $needle = mb_strtolower($search);

                return $collection->filter(function (array $row) use ($needle) {
                    $haystack = mb_strtolower(implode(' ', [
                        $row['title'] ?? '',
                        $row['barangay_name'] ?? '',
                        (string) ($row['calendar_year'] ?? ''),
                        $row['submitted_by'] ?? '',
                        $row['rejection_reason'] ?? '',
                    ]));

                    return str_contains($haystack, $needle);
                });
            })
            ->values();

        return response()->json([
            'data' => $rows->all(),
            'total' => $rows->count(),
            'retention_days' => RejectedAbyipService::RETENTION_DAYS,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        try {
            $row = $this->rejectedAbyipService->show($id);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Rejected ABYIP not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        try {
            $this->rejectedAbyipService->restore($request->user(), $id);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Unable to restore this ABYIP.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rejected ABYIP restored to pending.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->rejectedAbyipService->permanentlyDelete($request->user(), $id);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Unable to delete this ABYIP.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rejected ABYIP permanently deleted.',
        ]);
    }
}
