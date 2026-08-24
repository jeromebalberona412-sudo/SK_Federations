<?php

namespace App\Modules\Reports\Controllers;

use App\Modules\Reports\Services\ReportsService;
use App\Modules\Shared\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(private readonly ReportsService $service)
    {
    }

    public function index(Request $request): View
    {
        return view('Reports::index', [
            'user' => $request->user(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $reports = $this->service->listAll(
            $request->query('search'),
            $request->query('program'),
            $request->query('barangay'),
            $request->query('status')
        );

        return response()->json(['data' => $reports]);
    }

    public function download(int $id): StreamedResponse
    {
        return $this->service->stream($id, request()->boolean('download'));
    }
}
