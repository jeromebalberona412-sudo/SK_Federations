<?php

namespace App\Modules\BarangayMonitoring\Services;

use App\Models\Abyip;
use App\Models\ReportManagement;
use App\Modules\Profile\Models\Barangay;
use Illuminate\Support\Facades\Schema;

class BarangayMonitoringService
{
    /**
     * @return array<string, string>
     */
    public function slugToNameMap(): array
    {
        return [
            'alipit' => 'Alipit',
            'bagumbayan' => 'Bagumbayan',
            'bubukal' => 'Bubukal',
            'calios' => 'Calios',
            'duhat' => 'Duhat',
            'gatid' => 'Gatid',
            'jasaan' => 'Jasaan',
            'labuin' => 'Labuin',
            'malinao' => 'Malinao',
            'oogong' => 'Oogong',
            'pagsawitan' => 'Pagsawitan',
            'palasan' => 'Palasan',
            'patimbao' => 'Patimbao',
            'brgy-1-poblacion' => 'Poblacion I',
            'brgy-2-poblacion' => 'Poblacion II',
            'brgy-3-poblacion' => 'Poblacion III',
            'brgy-4-poblacion' => 'Poblacion IV',
            'brgy-5-poblacion' => 'Poblacion V',
            'san-jose' => 'San Jose',
            'san-juan' => 'San Juan',
            'san-pablo-norte' => 'San Pablo Norte',
            'san-pablo-sur' => 'San Pablo Sur',
            'santisima-cruz' => 'Santisima Cruz',
            'santo-angel-central' => 'Santo Angel Central',
            'santo-angel-norte' => 'Santo Angel Norte',
            'santo-angel-sur' => 'Santo Angel Sur',
        ];
    }

    public function nameFromSlug(string $slug): ?string
    {
        return $this->slugToNameMap()[$slug] ?? null;
    }

    public function resolveBarangayId(string $slug): ?int
    {
        $name = $this->nameFromSlug($slug);

        if ($name === null || ! Schema::hasTable('barangays')) {
            return null;
        }

        return Barangay::query()->where('name', $name)->value('id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAbyipReports(?int $barangayId): array
    {
        if ($barangayId === null || ! Schema::hasTable('abyip')) {
            return [];
        }

        return Abyip::query()
            ->documents()
            ->with(['creator:id,name', 'creator.officialProfile:id,user_id,position'])
            ->where('barangay_id', $barangayId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Abyip $document) => [
                'id' => $document->id,
                'name' => $document->document_title ?: 'ABYIP '.$document->fiscal_year,
                'title' => $document->document_title ?: 'ABYIP '.$document->fiscal_year,
                'fiscal_year' => $document->fiscal_year,
                'calendar_year' => $document->fiscal_year,
                'date_submitted' => $document->created_at?->toDateTimeString(),
                'submitted_by' => $document->creator?->name ?? 'N/A',
                'submitted_by_role' => $this->formatOfficialPosition($document->creator?->officialProfile?->position),
                'status' => strtolower((string) ($document->status ?? Abyip::STATUS_PENDING)),
                'rejection_reason' => $document->rejection_reason,
                'has_pdf' => filled($document->pdf_data),
                'file' => filled($document->pdf_data) ? url('/api/barangay-abyip/'.$document->id.'/file') : null,
                'file_url' => filled($document->pdf_data) ? url('/api/barangay-abyip/'.$document->id.'/file') : null,
            ])
            ->all();
    }

    /**
     * @return array{status: string, submitted_by: ?string, submitted_by_role: ?string, latest_report: ?array<string, mixed>}
     */
    public function resolveAbyipStatus(?int $barangayId): array
    {
        $reports = $this->getAbyipReports($barangayId);

        if ($reports === []) {
            return [
                'status' => 'not_submitted',
                'submitted_by' => null,
                'submitted_by_role' => null,
                'latest_report' => null,
            ];
        }

        $currentYear = (int) now()->format('Y');
        $currentYearReport = collect($reports)->first(
            fn (array $report) => (int) ($report['fiscal_year'] ?? 0) === $currentYear
        );
        $latest = $currentYearReport ?? $reports[0];
        $status = strtolower((string) ($latest['status'] ?? Abyip::STATUS_PENDING));

        if (! in_array($status, [Abyip::STATUS_PENDING, Abyip::STATUS_APPROVED, Abyip::STATUS_REJECTED], true)) {
            $status = Abyip::STATUS_PENDING;
        }

        return [
            'status' => $status,
            'submitted_by' => $latest['submitted_by'] ?? null,
            'submitted_by_role' => $latest['submitted_by_role'] ?? null,
            'latest_report' => $latest,
        ];
    }

    public function countBarangaysWithAbyipSubmission(): int
    {
        if (! Schema::hasTable('abyip')) {
            return 0;
        }

        return (int) Abyip::query()
            ->documents()
            ->whereNotNull('barangay_id')
            ->selectRaw('count(distinct barangay_id) as aggregate')
            ->value('aggregate');
    }

    private function formatOfficialPosition(?string $position): ?string
    {
        if ($position === null || trim($position) === '') {
            return null;
        }

        return match ($position) {
            'Chairperson' => 'SK Chairperson',
            'Secretary' => 'SK Secretary',
            'Treasurer' => 'SK Treasurer',
            'Kagawad' => 'SK Kagawad',
            default => $position,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getApprovedAccomplishments(?int $barangayId): array
    {
        if ($barangayId === null || ! Schema::hasTable('report_management')) {
            return [];
        }

        return ReportManagement::query()
            ->where('barangay_id', $barangayId)
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ReportManagement $report) => [
                'id' => $report->id,
                'title' => $report->program_name,
                'activity' => $report->activity_name,
                'program_code' => $report->program_code,
                'description' => $report->activity_name,
                'sector' => $report->program_code,
                'timeline' => $report->created_at?->toDateString(),
                'start_date' => $report->created_at?->toDateTimeString(),
                'year' => (int) $report->created_at?->format('Y'),
                'term' => $this->skTermForYear((int) $report->created_at?->format('Y')),
                'status' => 'Approved',
                'file_url' => route('api.reports.download', ['id' => $report->id]),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function accomplishmentYears(?int $barangayId): array
    {
        return collect($this->getApprovedAccomplishments($barangayId))
            ->pluck('year')
            ->unique()
            ->sortDesc()
            ->map(fn ($year) => (string) $year)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function accomplishmentTerms(?int $barangayId): array
    {
        return collect($this->getApprovedAccomplishments($barangayId))
            ->pluck('term')
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function skTermForYear(int $year): string
    {
        $base = 2022;
        $offset = max(0, $year - $base);
        $termStart = $base + (int) (floor($offset / 3) * 3);

        return $termStart.'-'.($termStart + 2);
    }
}
