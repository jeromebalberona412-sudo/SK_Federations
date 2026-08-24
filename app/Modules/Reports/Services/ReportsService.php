<?php

namespace App\Modules\Reports\Services;

use App\Models\ReportManagement;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsService
{
    private const DISK = 'public';

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(?string $search = null, ?string $programCode = null, ?string $barangay = null, ?string $status = null): array
    {
        $query = ReportManagement::query()
            ->with('barangay:id,name')
            ->orderByDesc('created_at');

        if ($programCode) {
            $query->where('program_code', $programCode);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($barangay) {
            $query->whereHas('barangay', function ($q) use ($barangay) {
                $q->where('name', $barangay);
            });
        }

        if ($search) {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('program_name', 'ilike', $term)
                    ->orWhere('activity_name', 'ilike', $term)
                    ->orWhere('file_name', 'ilike', $term)
                    ->orWhereHas('barangay', fn ($bq) => $bq->where('name', 'ilike', $term));
            });
        }

        return $query->get()->map(fn (ReportManagement $report) => $this->formatReport($report))->all();
    }

    public function stream(int $reportId, bool $download = false): StreamedResponse
    {
        $report = ReportManagement::query()->findOrFail($reportId);

        $disposition = $download ? 'attachment' : 'inline';

        return response()->file(
            $this->resolveAbsolutePath($report->file_path),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition.'; filename="'.addslashes($report->file_name).'"',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReport(ReportManagement $report): array
    {
        return [
            'id' => $report->id,
            'programCode' => $report->program_code,
            'programName' => $report->program_name,
            'activity' => $report->activity_name,
            'fileName' => $report->file_name,
            'status' => $report->status,
            'uploadedAt' => $report->created_at?->toIso8601String(),
            'barangay' => $report->barangay?->name ?? '—',
            'downloadUrl' => route('api.reports.download', ['id' => $report->id]),
        ];
    }

    private function resolveAbsolutePath(string $path): string
    {
        if (Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->path($path);
        }

        $roots = array_filter([
            env('REPORT_MANAGEMENT_STORAGE_ROOT'),
            realpath(base_path('../SK_Officials/storage/app/public')),
            realpath(base_path('../SK_Federations/storage/app/public')),
        ]);

        foreach ($roots as $root) {
            $candidate = rtrim((string) $root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        abort(404, 'Report file not found.');
    }
}
